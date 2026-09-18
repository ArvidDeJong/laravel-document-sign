<?php

namespace Darvis\Signer\Services;

use Darvis\Signer\Models\Document;
use Darvis\Signer\Models\Signer;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class SignatureStamper
{
    /**
     * Stamp every captured signature on the original PDF and store the result.
     *
     * @return string The storage path of the signed PDF.
     */
    public function stamp(Document $document): string
    {
        $disk = Storage::disk(config('signer.disk'));
        $originalFile = $this->toTempFile($disk->get($document->original_path), 'pdf');

        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($originalFile);
        $signersByPage = $document->signers->filter->hasSigned()->groupBy('page');

        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

            foreach ($signersByPage->get($page, collect()) as $signer) {
                $this->placeSignature($pdf, $signer, $size['width'], $size['height']);
            }
        }

        $signedPath = config('signer.storage_path').'/signed/'.$document->uuid.'.pdf';
        $disk->put($signedPath, $pdf->Output('S'));
        @unlink($originalFile);

        return $signedPath;
    }

    protected function placeSignature(Fpdi $pdf, Signer $signer, float $pageWidth, float $pageHeight): void
    {
        if ($signer->signature_path === null) {
            throw new RuntimeException("Signer [{$signer->uuid}] has no captured signature.");
        }

        $imageFile = $this->toTempFile(
            Storage::disk(config('signer.disk'))->get($signer->signature_path),
            'png'
        );

        $width = ($signer->width ?? config('signer.default_signature_width')) / 100 * $pageWidth;
        $x = $signer->x / 100 * $pageWidth;
        $y = $signer->y / 100 * $pageHeight;

        $pdf->Image($imageFile, $x, $y, $width, 0, 'PNG');
        @unlink($imageFile);
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
