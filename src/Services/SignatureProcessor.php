<?php

namespace Darvis\Signer\Services;

use Darvis\Signer\Enums\DocumentStatus;
use Darvis\Signer\Enums\SignerStatus;
use Darvis\Signer\Events\DocumentCompleted;
use Darvis\Signer\Events\SignerSigned;
use Darvis\Signer\Models\Signer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class SignatureProcessor
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected SignatureStamper $stamper,
    ) {}

    /**
     * Store the captured signature, mark the signer as signed and finish the
     * document when every signer is done.
     */
    public function sign(Signer $signer, string $signatureDataUrl, ?Request $request = null): Signer
    {
        $signer->update([
            'signature_path' => $this->storeSignatureImage($signer, $signatureDataUrl),
            'status' => SignerStatus::Signed,
            'signed_at' => now(),
        ]);

        $document = $signer->document;
        $this->auditLogger->log($document, 'document_signed', $signer, $request);
        event(new SignerSigned($signer));

        if ($document->isFullySigned()) {
            $this->completeDocument($signer);
        }

        return $signer;
    }

    protected function completeDocument(Signer $signer): void
    {
        $document = $signer->document->load('signers');

        $document->update([
            'signed_path' => $this->stamper->stamp($document),
            'status' => DocumentStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->auditLogger->log($document, 'document_completed');
        event(new DocumentCompleted($document));
    }

    protected function storeSignatureImage(Signer $signer, string $dataUrl): string
    {
        if (! preg_match('/^data:image\/png;base64,(.+)$/', $dataUrl, $matches)) {
            throw new InvalidArgumentException('Signature must be a base64 encoded PNG data URL.');
        }

        $contents = base64_decode($matches[1], true);

        if ($contents === false) {
            throw new InvalidArgumentException('Signature data URL contains invalid base64 data.');
        }

        $path = config('signer.storage_path').'/signatures/'.$signer->uuid.'.png';
        Storage::disk(config('signer.disk'))->put($path, $contents);

        return $path;
    }
}
