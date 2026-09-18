<?php

namespace Darvis\Signer\Services;

use Darvis\Signer\Models\Document;
use Darvis\Signer\Models\Signer;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class SignatureStamper
{
    public function __construct(
        protected SignerConfig $config,
    ) {}

    /**
     * Stamp every captured signature on the original PDF and store the result.
     *
     * @return string The storage path of the signed PDF.
     */
    public function stamp(Document $document): string
    {
        $disk = Storage::disk($this->config->disk());
        $originalFile = $this->toTempFile($this->read($document->original_path), 'pdf');

        $pdf = new Fpdi;
        $pageCount = $pdf->setSourceFile($originalFile);
        $signersByPage = $document->signers
            ->filter(fn (Signer $signer): bool => $signer->hasSigned())
            ->groupBy('page');

        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);

            if (! is_array($size)) {
                throw new RuntimeException("Unable to read the size of page {$page}.");
            }

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

            foreach ($signersByPage->get($page, collect()) as $signer) {
                $this->placeSignature($pdf, $signer, $size['width'], $size['height']);
            }
        }

        $signedPath = $this->config->storagePath().'/signed/'.$document->uuid.'.pdf';
        $disk->put($signedPath, $pdf->Output('S'));
        @unlink($originalFile);

        return $signedPath;
    }

    protected function placeSignature(Fpdi $pdf, Signer $signer, float $pageWidth, float $pageHeight): void
    {
        if ($signer->signature_path === null) {
            throw new RuntimeException("Signer [{$signer->uuid}] has no captured signature.");
        }

        $imageFile = $this->toTempFile($this->read($signer->signature_path), 'png');

        $width = ($signer->width ?? $this->config->defaultSignatureWidth()) / 100 * $pageWidth;
        $x = $signer->x / 100 * $pageWidth;
        $y = $signer->y / 100 * $pageHeight;

        $pdf->Image($imageFile, $x, $y, $width, 0, 'PNG');
        @unlink($imageFile);
    }

    /**
     * Read a file from the configured disk, failing loudly when it is missing.
     */
    protected function read(string $path): string
    {
        $contents = Storage::disk($this->config->disk())->get($path);

        if ($contents === null) {
            throw new RuntimeException("File [{$path}] is missing from the signer disk.");
        }

        return $contents;
    }

    protected function toTempFile(string $contents, string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'signer').'.'.$extension;

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Unable to write temporary file for stamping.');
        }

        return $path;
    }
}
