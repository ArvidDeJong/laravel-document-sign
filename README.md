# darvis/laravel-document-sign

[![Latest Version](https://img.shields.io/packagist/v/darvis/laravel-document-sign.svg)](https://packagist.org/packages/darvis/laravel-document-sign)
[![Tests](https://github.com/ArvidDeJong/laravel-document-sign/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/laravel-document-sign/actions/workflows/tests.yml)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![Total downloads](https://img.shields.io/packagist/dt/darvis/laravel-document-sign.svg)](https://packagist.org/packages/darvis/laravel-document-sign)
[![License](https://img.shields.io/packagist/l/darvis/laravel-document-sign.svg)](LICENSE)

Sign PDF documents in Laravel with a flow like DocuSign, inside your own application
and on your own storage. Store a document, invite signers by mail, let them sign
through a secure link on a ready-made signing page, and get the PDF back with every
signature stamped on it and a full audit trail.

It also ships a complete portal with login, dashboard, customer and document
management, so a fresh Laravel installation works as a standalone signing system
after `composer require` and `php artisan migrate`.

An independent open-source package, not affiliated with DocuSign.

## Features

- **Fluent builder**: `Signer::document($path)->addSigner(…)->send()` stores the PDF, creates the signers and mails the invitations
- **Signing page** with a signature canvas, reached through a temporary signed link that expires
- **Stamping**: once everyone has signed, every signature is placed on the PDF with FPDI; positions are percentages, so they work on any paper size
- **Audit trail** of every step with IP address and user agent
- **Events** `SignerSigned` and `DocumentCompleted` to hook your own follow-up in
- **Portal** with login, dashboard, customers with contacts and document management, on by default and switchable off
- **Dutch translation** of the portal and the signing page

## Installation

```bash
composer require darvis/laravel-document-sign
php artisan migrate
```

Requires PHP 8.2+ with the GD extension and Laravel 11, 12 or 13. The portal logs
in with a user from your application's `users` table; create one and open `/portal`.
See [Installation & configuration](docs/installation.md) for publishing, every
config key and the first portal user.

## Usage

```php
use Darvis\Signer\Facades\Signer;

$document = Signer::document(storage_path('contracts/contract.pdf'))
    ->title('Freelance agreement')
    ->addSigner('Alice Jansen', 'alice@example.com', page: 1, x: 10, y: 80)
    ->addSigner('Bob de Vries', 'bob@example.com', page: 1, x: 55, y: 80)
    ->send();
```

Every signer receives a mail with a temporary signed link to the signing page, draws
a signature and confirms. When everyone has signed, the package stamps all
signatures on the PDF, stores it on the configured disk and dispatches
`Darvis\Signer\Events\DocumentCompleted`.

```php
$document->refresh();

$document->status;          // DocumentStatus: Draft, Pending, Completed, Cancelled
$document->isFullySigned();
$document->signed_path;     // the final PDF on the configured disk
$document->auditEvents;     // document_created, invitation_sent, document_signed, document_completed
```

Positions are percentages of the page measured from the top left, so the same
numbers work on any paper size. Set `SIGNER_PORTAL_ENABLED=false` to use the signing
flow without the portal.

## Documentation

Full documentation: **https://arviddejong.github.io/laravel-document-sign/**

| Topic | |
| --- | --- |
| [Installation & configuration](docs/installation.md) | Requirements, migrations, publishing, every config key, the first portal user |
| [Signing flow](docs/signing.md) | The builder, positions, the signing page, status and downloads |
| [Events & audit trail](docs/events-and-audit.md) | The events and what the audit trail records |
| [Portal](docs/portal.md) | What it offers, who can log in, running without it |

Or start at the [documentation index](docs/README.md), or read the [FAQ](https://arviddejong.github.io/laravel-document-sign/faq.html).

## Laravel Boost

The package ships a [Laravel Boost](https://laravel.com/docs/boost) guideline with the
rules that matter when writing code against it. Run `php artisan boost:install`, or
`php artisan boost:update --discover` in a project that already uses Boost.

## Development

```bash
composer test      # Pest
composer lint      # Pint, check only (composer format to fix)
composer analyse   # Larastan, level 8
```

GitHub Actions runs the tests on PHP 8.2 to 8.4 against Laravel 11, 12 and 13, with both
the lowest and the latest allowed dependencies. See the [changelog](CHANGELOG.md) for
release notes.

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md). Found a security problem? Please report it privately, see [SECURITY.md](SECURITY.md).

## Author

**Arvid de Jong** · [info@arvid.nl](mailto:info@arvid.nl)

## License

MIT, see [LICENSE](LICENSE).
