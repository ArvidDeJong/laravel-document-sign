---
title: "Installation & configuration"
description: "Install darvis/laravel-document-sign: requirements, migrations, publishing config and views, every key in config/signer.php and creating the first portal user."
nav_order: 2
---

# Installation & configuration

## Requirements

- PHP 8.2+ with the GD extension (the captured signatures are PNG images)
- Laravel 11, 12 or 13
- A configured mailer, because signers are invited by mail
- A filesystem disk for the documents; `local` works out of the box

## Installation

```bash
composer require darvis/laravel-document-sign
php artisan migrate
```

The service provider and the `Signer` facade are registered through package discovery. The migrations create four tables, all prefixed `signer_`: `signer_customers`, `signer_contacts`, `signer_documents`, `signer_signers` and `signer_audit_events`.

Publishing is optional:

```bash
php artisan vendor:publish --tag=signer-config   # config/signer.php
php artisan vendor:publish --tag=signer-views    # resources/views/vendor/signer
```

Publish the views only when you want to change them; published views stop receiving updates from the package.

## The first portal user

The portal logs in with a user from your application's `users` table, through the guard in `signer.portal.guard`. Create one if there is none yet:

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

The portal is then available at `/portal`. See [Portal](portal.md).

## Configuration

All keys live in `config/signer.php`, in alphabetical order. Values are read through `Darvis\Signer\Support\SignerConfig`.

| Key | Env variable | Default | Description |
| --- | --- | --- | --- |
| `default_signature_width` | | `20.0` | Width of a placed signature in percent of the page width, when `addSigner()` gets no `width`; the height follows the aspect ratio of the drawn signature |
| `disk` | `SIGNER_DISK` | `local` | Filesystem disk for originals, signature images and signed PDFs |
| `link_expires_after_hours` | `SIGNER_LINK_EXPIRES_AFTER_HOURS` | `72` | Hours a signing link stays valid after it was sent |
| `portal.enabled` | `SIGNER_PORTAL_ENABLED` | `true` | Register the portal routes |
| `portal.guard` | | `web` | Auth guard the portal logs in against |
| `portal.middleware` | | `['web']` | Middleware of the portal routes |
| `portal.prefix` | | `portal` | URL prefix of the portal |
| `route_middleware` | | `['web']` | Middleware of the signing routes |
| `route_prefix` | | `sign` | URL prefix of the signing routes |
| `storage_path` | `SIGNER_STORAGE_PATH` | `signer` | Base path within the disk; files land in `originals/`, `signatures/` and `signed/` below it |

## Translations

The portal and the signing page are English, with a Dutch translation in `lang/nl.json`. Set the application locale to `nl` for a Dutch interface. Texts go through `__()`, so you can add another language with a JSON file of your own in `lang/`.

## Laravel Boost

The package ships [Laravel Boost](https://laravel.com/docs/boost) resources: a guideline and a `laravel-document-sign-development` skill, so an AI assistant in your application knows the signing flow, its failure modes and how to test it. Run `php artisan boost:install`, or `php artisan boost:update --discover` in a project that already uses Boost.
