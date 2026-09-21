---
title: Home
nav_order: 1
description: "darvis/laravel-document-sign: sign PDF documents in Laravel with a DocuSign like flow, mailed invitations, a signing page with a signature canvas, stamping on the PDF, an audit trail and an optional portal."
permalink: /
---

# darvis/laravel-document-sign

Sign PDF documents in **Laravel** with a flow like DocuSign, inside your own application and on your own storage. You store a document, invite signers by mail, they sign through a secure link on a ready-made signing page, and the package stamps every signature on the PDF with a full audit trail.

It also ships a complete portal with login, dashboard, customer and document management, so a fresh Laravel installation works as a standalone signing system after `composer require` and `php artisan migrate`.

This is an independent open-source package, not affiliated with DocuSign.

```bash
composer require darvis/laravel-document-sign
php artisan migrate
```

Requires PHP 8.2+ with the GD extension and Laravel 11, 12 or 13.

## Features

- **Fluent builder**: `Signer::document($path)->addSigner(…)->send()` stores the PDF, creates the signers and mails the invitations
- **Signing page** with a signature canvas, reached through a temporary signed link that expires
- **Stamping**: once everyone has signed, every signature is placed on the PDF with FPDI; positions are percentages, so they work on any paper size
- **Audit trail** of every step with IP address and user agent
- **Events** `SignerSigned` and `DocumentCompleted` to hook your own follow-up in
- **Portal** with login, dashboard, customers with contacts and document management, on by default and switchable off
- **Dutch translation** of the portal and the signing page, and a Laravel Boost guideline and skill for AI tooling in your app

## Quick example

```php
use Darvis\Signer\Facades\Signer;

$document = Signer::document(storage_path('contracts/contract.pdf'))
    ->title('Freelance agreement')
    ->addSigner('Alice Jansen', 'alice@example.com', page: 1, x: 10, y: 80)
    ->addSigner('Bob de Vries', 'bob@example.com', page: 1, x: 55, y: 80)
    ->send();
```

## Read next

- [Installation & configuration](installation.md): requirements, publishing, every config key and the first portal user
- [Signing flow](signing.md): the builder, positions, the signing page, status and downloads
- [Events & audit trail](events-and-audit.md): reacting to signatures and what the audit trail records
- [Portal](portal.md): what it offers, who can log in, and running without it
- [FAQ](faq.md)
