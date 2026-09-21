<?php

namespace Darvis\Signer\Support;

use InvalidArgumentException;

/**
 * Decodes the `data:image/png;base64,…` URL the signature pad submits and
 * checks that it really is a PNG the stamper can place on the PDF.
 */
class SignatureDataUrl
{
    public const PREFIX = 'data:image/png;base64,';

    /**
     * Larger than any signature pad, a high density tablet included.
     */
    public const MAX_DIMENSION = 5000;

    protected const PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    /**
     * @return string The binary PNG.
     *
     * @throws InvalidArgumentException
     */
    public static function decode(string $dataUrl, int $maxKilobytes): string
    {
        if (! str_starts_with($dataUrl, self::PREFIX)) {
            throw new InvalidArgumentException('Signature must be a base64 encoded PNG data URL.');
        }

        $encoded = substr($dataUrl, strlen(self::PREFIX));

        // Checked on the encoded length first, so an oversized payload is never decoded.
        if (strlen($encoded) > (int) ceil($maxKilobytes * 1024 / 3) * 4) {
            throw new InvalidArgumentException("Signature image is larger than {$maxKilobytes} kilobytes.");
        }

        $contents = base64_decode($encoded, true);

        if ($contents === false || $contents === '') {
            throw new InvalidArgumentException('Signature data URL contains invalid base64 data.');
        }

        if (strlen($contents) > $maxKilobytes * 1024) {
            throw new InvalidArgumentException("Signature image is larger than {$maxKilobytes} kilobytes.");
        }

        if (! str_starts_with($contents, self::PNG_SIGNATURE)) {
            throw new InvalidArgumentException('Signature image is not a PNG file.');
        }

        $size = @getimagesizefromstring($contents);

        if ($size === false || $size[2] !== IMAGETYPE_PNG || $size['mime'] !== 'image/png') {
            throw new InvalidArgumentException('Signature image is not a PNG file.');
        }

        if ($size[0] < 1 || $size[1] < 1 || $size[0] > self::MAX_DIMENSION || $size[1] > self::MAX_DIMENSION) {
            throw new InvalidArgumentException('Signature image has unusable dimensions.');
        }

        // FPDF, which places the image on the PDF, reads neither 16 bit nor interlaced
        // PNG files. Refused here, or the document would fail at the last signature.
        $header = unpack('Cdepth/Ccolour/Ccompression/Cfilter/Cinterlace', substr($contents, 24, 5));

        if (
            substr($contents, 12, 4) !== 'IHDR'
            || $header === false
            || $header['depth'] > 8
            || $header['compression'] !== 0
            || $header['filter'] !== 0
            || $header['interlace'] !== 0
        ) {
            throw new InvalidArgumentException('Signature image is a PNG variant that cannot be placed on a PDF.');
        }

        return $contents;
    }
}
