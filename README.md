# darvis/laravel-document-sign

[![Latest Version](https://img.shields.io/packagist/v/darvis/laravel-document-sign.svg)](https://packagist.org/packages/darvis/laravel-document-sign)
[![Tests](https://github.com/ArvidDeJong/laravel-document-sign/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/laravel-document-sign/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/darvis/laravel-document-sign/php.svg)](https://packagist.org/packages/darvis/laravel-document-sign)
[![License](https://img.shields.io/packagist/l/darvis/laravel-document-sign.svg)](LICENSE)

Let people sign a PDF document inside your own Laravel application, with a flow like
DocuSign. You hand the package a PDF and the signers; it mails every signer a link,
shows a signing page where they draw a signature, places all signatures on the PDF once
everyone has signed, and records the steps in an audit trail. An optional portal lets
you send documents without writing code. The documents stay on your own storage.

An independent open-source package, not affiliated with DocuSign. A signature is a
drawn image on the page backed by the audit trail; the package does not create
qualified electronic signatures and does not sign the PDF cryptographically.

## Features

- **Fluent builder**: `Signer::document($path)->addSigner(…)->send()` stores the PDF, creates the signers and mails the invitations
- **Signing page** with a signature canvas, reached through a temporary signed link; once it expires the PDF and submitting a signature stop working too
- **Atomic signing**: the signature is validated as a real PNG, and a failed stamp rolls everything back so the signer can try again
- **Stamping**: once everyone has signed, every signature is placed on the PDF with FPDI; positions are percentages, so they work on any paper size
- **Audit trail** of every step; the signing step records the IP address and user agent of the signer
- **Events** `SignerSigned` and `DocumentCompleted` to hook your own follow-up in
- **Portal** with a rate limited login, dashboard, customers with contacts and document management, on by default and switchable off
- **Dutch translation** of the portal and the signing page

## Requirements

- PHP 8.2 or higher with the GD extension
- Laravel 11, 12 or 13
- A mailer, because signers are invited by mail; `MAIL_MAILER=log` is enough to try it

## Installation

```bash
composer require darvis/laravel-document-sign
php artisan migrate
```

[Installation & configuration](https://arviddejong.github.io/laravel-document-sign/installation.html)
has the steps up to a first signed test document, the publish tags and every config key.

## Who can open the portal

The portal is on by default at `/portal` and logs in with the users of your
application. **Every user who can log in sees and can delete every customer and
document; there are no roles.** Add your own middleware to `signer.portal.middleware`
or set `SIGNER_PORTAL_ENABLED=false` when that is not what you want, see
[Portal](https://arviddejong.github.io/laravel-document-sign/portal.html).

## Quick start

```php
use Darvis\Signer\Facades\Signer;

$document = Signer::document(storage_path('app/contracts/contract.pdf'))
    ->title('Freelance agreement')
    ->addSigner('Alice Jansen', 'alice@example.com', page: 1, x: 10, y: 80)
    ->addSigner('Bob de Vries', 'bob@example.com', page: 1, x: 55, y: 80)
    ->send();
```

Every signer receives a mail with a temporary signed link to the signing page, draws
a signature and confirms. When everyone has signed, the package places all signatures
on the PDF, stores it on the configured disk and dispatches
`Darvis\Signer\Events\DocumentCompleted`. Positions are percentages of the page
measured from the top left, so the same numbers work on any paper size.

```php
$document->refresh();

$document->status;          // DocumentStatus: Draft, Pending, Completed, Cancelled
$document->isFullySigned();
$document->signed_path;     // the final PDF on the configured disk
$document->auditEvents;     // document_created, invitation_sent, document_signed, document_completed
```

## Documentation

Full documentation: **https://arviddejong.github.io/laravel-document-sign/**

| Page | |
| --- | --- |
| [Installation & configuration](https://arviddejong.github.io/laravel-document-sign/installation.html) | From `composer require` to a first signed test document, and every config key |
| [Quick start](https://arviddejong.github.io/laravel-document-sign/quick-start.html) | One complete example: send a contract and pick up the signed PDF |
| [Signing flow](https://arviddejong.github.io/laravel-document-sign/signing.html) | The builder, positions, link expiry, failed signatures, status and downloads |
| [Events & audit trail](https://arviddejong.github.io/laravel-document-sign/events-and-audit.html) | The two events and what the audit trail records |
| [Portal](https://arviddejong.github.io/laravel-document-sign/portal.html) | What it offers, who can log in and what they see, running without it |
| [Testing](https://arviddejong.github.io/laravel-document-sign/testing.html) | Test your own code around the signing flow without sending mail |
| [Troubleshooting](https://arviddejong.github.io/laravel-document-sign/troubleshooting.html) | Error messages and symptoms with cause and fix |
| [FAQ](https://arviddejong.github.io/laravel-document-sign/faq.html) | Short answers to common questions |

## Laravel Boost

The package ships [Laravel Boost](https://laravel.com/docs/boost) resources: a guideline
and a `laravel-document-sign-development` skill. Run `php artisan boost:install`, or
`php artisan boost:update --discover` in a project that already uses Boost.

## Testing

```bash
composer test      # Pest
composer lint      # Pint, check only
composer format    # Pint, fixes the style
composer analyse   # Larastan, level 8
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Support the package

If darvis/laravel-document-sign saves you time, a star on [GitHub](https://github.com/ArvidDeJong/laravel-document-sign) or a favourite on [Packagist](https://packagist.org/packages/darvis/laravel-document-sign) helps other developers find it.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## Security

Found a security problem? Please report it privately, see [SECURITY.md](SECURITY.md).

## License

MIT, see [LICENSE](LICENSE).
