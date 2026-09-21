<?php

namespace Darvis\Signer\Services;

use Darvis\Signer\Enums\DocumentStatus;
use Darvis\Signer\Enums\SignerStatus;
use Darvis\Signer\Events\DocumentCompleted;
use Darvis\Signer\Events\SignerSigned;
use Darvis\Signer\Exceptions\SigningFailed;
use Darvis\Signer\Models\Document;
use Darvis\Signer\Models\Signer;
use Darvis\Signer\Support\SignatureDataUrl;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SignatureProcessor
{
    /**
     * Files written during the current sign() call, removed again when it fails.
     *
     * @var array<int, string>
     */
    protected array $writtenPaths = [];

    public function __construct(
        protected AuditLogger $auditLogger,
        protected SignatureStamper $stamper,
        protected SignerConfig $config,
    ) {}

    /**
     * Store the captured signature, mark the signer as signed and finish the
     * document when every signer is done.
     *
     * Everything happens in one database transaction: when storing or stamping
     * fails nothing is kept, so the signer can submit again. The events are
     * dispatched after the commit.
     *
     * @throws InvalidArgumentException When the data URL is not a usable PNG.
     * @throws SigningFailed When storing or stamping failed and was rolled back.
     */
    public function sign(Signer $signer, string $signatureDataUrl, ?Request $request = null): Signer
    {
        // Refused before anything is written, so a bad payload never opens a transaction.
        $this->decode($signatureDataUrl);

        $this->writtenPaths = [];
        $attributes = $signer->getRawOriginal();

        try {
            $outcome = $signer->getConnection()->transaction(
                fn (): ?bool => $this->signInTransaction($signer, $signatureDataUrl, $request),
            );
        } catch (Throwable $e) {
            $signer->setRawAttributes($attributes, true);
            $signer->unsetRelation('document');
            $this->deleteWrittenFiles();

            throw SigningFailed::for($signer, $e);
        }

        if ($outcome === null) {
            return $signer;
        }

        event(new SignerSigned($signer));

        if ($outcome) {
            event(new DocumentCompleted($signer->document));
        }

        return $signer;
    }

    /**
     * @return bool|null Null when the signer had signed already, otherwise whether the document was completed.
     */
    protected function signInTransaction(Signer $signer, string $signatureDataUrl, ?Request $request): ?bool
    {
        // The document row is locked first, so two signers of one document sign
        // one after the other and the last one always sees that everyone is done.
        $document = Document::query()->lockForUpdate()->findOrFail($signer->document_id);
        $current = Signer::query()->lockForUpdate()->findOrFail($signer->id);

        if ($current->hasSigned()) {
            // A double submit: the first request won while this one waited for the lock.
            $signer->setRawAttributes($current->getAttributes(), true);

            return null;
        }

        $signer->setRelation('document', $document);

        $signer->update([
            'signature_path' => $this->storeSignatureImage($signer, $signatureDataUrl),
            'status' => SignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $this->auditLogger->log($document, 'document_signed', $signer, $request);

        if (! $document->isFullySigned()) {
            return false;
        }

        $this->completeDocument($signer);

        return true;
    }

    protected function completeDocument(Signer $signer): void
    {
        $document = $signer->document->load('signers');

        $signedPath = $this->stamper->stamp($document);
        $this->writtenPaths[] = $signedPath;

        $document->update([
            'signed_path' => $signedPath,
            'status' => DocumentStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->auditLogger->log($document, 'document_completed');
    }

    protected function storeSignatureImage(Signer $signer, string $dataUrl): string
    {
        $contents = $this->decode($dataUrl);
        $path = $this->config->storagePath().'/signatures/'.$signer->uuid.'.png';

        $this->writtenPaths[] = $path;

        if (! Storage::disk($this->config->disk())->put($path, $contents)) {
            throw new RuntimeException("Signature image could not be written to [{$path}].");
        }

        return $path;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function decode(string $dataUrl): string
    {
        return SignatureDataUrl::decode($dataUrl, $this->config->signatureMaxKilobytes());
    }

    protected function deleteWrittenFiles(): void
    {
        try {
            Storage::disk($this->config->disk())->delete($this->writtenPaths);
        } catch (Throwable $e) {
            // The original failure matters more than a file that could not be removed.
            report($e);
        }

        $this->writtenPaths = [];
    }
}
