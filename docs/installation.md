---
title: "Installation & configuration"
description: "Install darvis/laravel-document-sign step by step: requirements, migrations, mail, the first portal user, a check that signing works, and every config key."
nav_order: 2
---

# Installation & configuration

## Requirements

- PHP 8.2 or higher with the GD extension (`ext-gd`). Composer refuses to install the package without it.
- Laravel 11, 12 or 13
- A mailer in your `.env`, because signers are invited by mail. A new Laravel application has `MAIL_MAILER=log`, which writes every mail to `storage/logs/laravel.log`; that is enough to try the package.
- A filesystem disk for the documents. The default is `local`, which needs no setup.

The PDF libraries that come along, `setasign/fpdf` and `setasign/fpdi`, need the zlib extension, which a standard PHP installation has.

## Step 1. Install the package

```bash
composer require darvis/laravel-document-sign
```

The service provider and the `Signer` facade are registered through Laravel's package discovery; there is nothing to add to `bootstrap/providers.php`.

## Step 2. Run the migrations

```bash
php artisan migrate
```

The migrations are loaded from the package and create five tables, all prefixed `signer_`: `signer_customers`, `signer_contacts`, `signer_documents`, `signer_signers` and `signer_audit_events`. They cannot be published.

## Step 3. Check the application URL and the mailer

Set `APP_URL` in `.env` to the address people use to reach the application. The signing link in the mail is a signed URL: Laravel computes a signature over the full address, so a link built with another host or scheme than the one the signer opens answers "403 Invalid signature".

For real mail, set the `MAIL_*` values of your mail provider as described in the [Laravel mail documentation](https://laravel.com/docs/mail). The invitation is sent during `send()`, not through the queue.

## Step 4. Create the first portal user

The portal logs in with a user from your application's `users` table, through the guard in `signer.portal.guard`. A guard is Laravel's name for a way of logging in; `web` is the normal session login. Skip this step when you turn the portal off. Create a user if there is none yet:

```bash
php artisan tinker
```

```php
App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@example.com',
    'password' => Hash::make('change-me'),
]);
```

The portal is then available at `/portal`. Every user in that table can log in and sees everything; read [Portal](portal.md#who-can-log-in-and-what-they-see) before you put this on a server with other users.

## Check that it works

Run this in `php artisan tinker`. It builds a one page PDF with FPDF, which the package installs, and sends it to one signer:

```php
$pdf = new FPDF;
$pdf->AddPage();
$pdf->SetFont('Helvetica', '', 14);
$pdf->Cell(0, 10, 'Test contract');
$pdf->Output('F', storage_path('app/test-contract.pdf'));

$document = Darvis\Signer\Facades\Signer::document(storage_path('app/test-contract.pdf'))
    ->title('Test contract')
    ->addSigner('Test signer', 'you@example.com')
    ->send();

$document->status->value;                          // "pending"
$document->auditEvents()->pluck('event')->all();   // ["document_created", "invitation_sent"]
```

Expect `"pending"` and the two audit events. With `MAIL_MAILER=log` the mail "Signature requested: Test contract" is now in `storage/logs/laravel.log`. Open the link from the mail in a browser, draw a signature and press "Sign document". You should land on a page that says "Thank you, Test signer!", and this returns `"completed"`:

```php
$document->refresh()->status->value;
```

The signed PDF is on the configured disk under `signer/signed/`.

Something else happened? [Troubleshooting](troubleshooting.md) lists the messages, for example "PDF file not found", "403 Invalid signature" and a missing table.

## Publishing the config and the views

Publishing is optional. Both commands copy files into your application:

```bash
php artisan vendor:publish --tag=signer-config   # config/signer.php
php artisan vendor:publish --tag=signer-views    # resources/views/vendor/signer
```

Publish the views only when you want to change them. A published view wins over the one in the package, so it no longer follows updates of the package. A published `config/signer.php` that misses a newer key keeps working: the default of the package applies to the missing key.

## Configuration

All keys live in `config/signer.php`, in alphabetical order. None of them is needed to start. In your own code, read them through the accessor class `Darvis\Signer\Support\SignerConfig`, for example `app(SignerConfig::class)->disk()`.

| Key | Env variable | Default | Description |
| --- | --- | --- | --- |
| `default_signature_width` | | `20.0` | Width of a placed signature in percent of the page width, when `addSigner()` gets no `width`; the height follows the aspect ratio of the drawn signature |
| `disk` | `SIGNER_DISK` | `local` | Filesystem disk for originals, signature images and signed PDFs |
| `link_expires_after_hours` | `SIGNER_LINK_EXPIRES_AFTER_HOURS` | `72` | Hours a signing link stays valid after the invitation was last sent; after that the signing page, the PDF and submitting a signature all answer 403 |
| `portal.enabled` | `SIGNER_PORTAL_ENABLED` | `true` | Register the portal routes |
| `portal.guard` | | `web` | Auth guard the portal logs in against; it has to be a session guard, because the login calls `attempt()` |
| `portal.middleware` | | `['web']` | Middleware of all portal routes, the login included; keep `web` in it, the portal needs the session |
| `portal.prefix` | | `portal` | URL prefix of the portal |
| `route_middleware` | | `['web']` | Middleware of the signing routes; keep `web` in it for the session, CSRF and route model binding |
| `route_prefix` | | `sign` | URL prefix of the signing routes |
| `signature_max_kilobytes` | `SIGNER_SIGNATURE_MAX_KILOBYTES` | `512` | Largest signature image a signer may submit, in kilobytes of decoded PNG data |
| `storage_path` | `SIGNER_STORAGE_PATH` | `signer` | Base path within the disk; files land in `originals/`, `signatures/` and `signed/` below it |

The prefixes, the middleware and `portal.enabled` are read when the routes are registered. After a change run `php artisan config:clear`, and `php artisan route:clear` when you cache routes.

## Translations

The portal and the signing page are English, with a Dutch translation in `lang/nl.json`. Set the application locale to `nl` for a Dutch interface. Texts go through `__()`, so you can add another language with a JSON file of your own, for example `lang/de.json` in your application, with the English text as the key.

## Laravel Boost

The package ships [Laravel Boost](https://laravel.com/docs/boost) resources: a guideline and a `laravel-document-sign-development` skill, so an AI assistant in your application knows the signing flow, its failure modes and how to test it. Run `php artisan boost:install`, or `php artisan boost:update --discover` in a project that already uses Boost.
