<?php

use Darvis\Signer\Support\DownloadName;

it('turns a title into a safe file name', function (string $title, string $expected) {
    expect(DownloadName::forTitle($title))->toBe($expected);
})->with([
    'a plain title' => ['Freelance agreement', 'Freelance agreement.pdf'],
    'a slash' => ['Contract 2026/09', 'Contract 2026-09.pdf'],
    'a backslash' => ['Contract\\2026', 'Contract-2026.pdf'],
    'a path' => ['../../etc/passwd', 'etc-passwd.pdf'],
    'control characters' => ["Contract\r\nX-Header: 1\0", 'Contract X-Header: 1.pdf'],
    'only separators' => ['//', 'document.pdf'],
    'an empty title' => ['', 'document.pdf'],
    'invalid utf-8' => ["\xC3\x28", 'document.pdf'],
    'accents stay' => ['Café contract', 'Café contract.pdf'],
]);
