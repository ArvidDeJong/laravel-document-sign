---
title: "Home"
nav_order: 1
description: "Let people sign a PDF in your Laravel app: mailed invitation, drawn signature placed on the PDF, audit trail, optional portal. What it does and does not do."
permalink: /
---

# darvis/laravel-document-sign

This package lets people sign a PDF document inside your own **Laravel** application, with a flow like DocuSign. You hand it a PDF and the signers; it mails every signer a link, shows a signing page where they draw a signature, places all signatures on the PDF once everyone has signed, and records the steps in an audit trail.

It also ships an optional portal (login, dashboard, customers, documents) for sending documents without writing code.

This is an independent open-source package, not affiliated with DocuSign.

## Who it is for

Laravel developers who want contracts, quotes or forms signed by customers, with the documents on their own storage and the data in their own database.

## What it does not do

- It does not create a qualified or advanced electronic signature, and it does not sign the PDF cryptographically. A signature is a drawn image placed on the page, backed by the audit trail.
- It has no reminder mails, no "decline" button for signers and no signing order: every signer is invited at the same moment.
- It has no method to send an invitation again; you do that in two lines, see [When the link expires](signing.md#when-the-link-expires).
- The portal has no roles. Every user who can log in sees and can delete every customer and every document, see [Portal](portal.md#who-can-log-in-and-what-they-see).

## Requirements

- PHP 8.2 or higher with the GD extension
- Laravel 11, 12 or 13

## Install

```bash
composer require darvis/laravel-document-sign
php artisan migrate
```

Then follow [Installation & configuration](installation.md) to check that it works.

## Features

- **Fluent builder**: `Signer::document($path)->addSigner(…)->send()` stores the PDF, creates the signers and mails the invitations
- **Signing page** with a signature canvas, reached through a temporary signed link; once it expires the PDF and submitting a signature stop working too
- **Atomic signing**: the signature is validated as a real PNG, and a failed stamp rolls everything back so the signer can try again
- **Stamping**: once everyone has signed, every signature is placed on the PDF with FPDI; positions are percentages, so they work on any paper size
- **Audit trail** of every step; the signing step records the IP address and user agent of the signer
- **Events** `SignerSigned` and `DocumentCompleted` to hook your own follow-up in
- **Portal** with a rate limited login, dashboard, customers with contacts and document management, on by default and switchable off
- **Dutch translation** of the portal and the signing page, and a Laravel Boost guideline and skill for AI tooling in your app

## Read next

- [Installation & configuration](installation.md): the steps from `composer require` to a first signed test document, and every config key
- [Quick start](quick-start.md): one complete example that sends a contract and picks up the signed PDF
- [Signing flow](signing.md): the builder, positions, what signers see, link expiry, status and downloads
- [Events & audit trail](events-and-audit.md): reacting to signatures and what the audit trail records
- [Portal](portal.md): what it offers, who can log in and what they see, running without it
- [Testing](testing.md): test your own code around the signing flow without sending mail
- [Troubleshooting](troubleshooting.md): error messages and symptoms, with cause and fix
- [FAQ](faq.md): short answers to common questions
