<?php

namespace Darvis\Signer\Support;

/**
 * Turns a document title into a file name that is safe in a
 * Content-Disposition header: no path separators, no control characters.
 */
class DownloadName
{
    public static function forTitle(string $title, string $extension = 'pdf'): string
    {
        $name = preg_replace('/[\/\\\\]+/u', '-', $title) ?? '';
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name) ?? '';
        $name = trim((string) preg_replace('/\s+/u', ' ', $name), " .-\t\n\r\0\x0B");

        if ($name === '') {
            $name = 'document';
        }

        return $name.'.'.$extension;
    }
}
