---
title: "Troubleshooting"
description: "Error messages and symptoms with cause and fix: 403 Invalid signature, PDF file not found, a signature missing on the PDF, FPDI errors and portal login."
nav_order: 8
---

# Troubleshooting

Each entry is a symptom, the cause and the fix. The messages are quoted as the code writes them, so you can search for them.

## Sending

### PDF file not found at [<path>].

An `InvalidArgumentException` from `Signer::document()`. The path is not a file on the server. The builder takes a local file path, for example `storage_path('app/contracts/contract.pdf')`, not a path on a Laravel disk. Write a PDF from S3 or another disk to a local temporary file first.

### Add at least one signer before sending.

An `InvalidArgumentException` from `send()`. Call `addSigner()` at least once before `send()`.

### PDF file at [<path>] could not be read.

An `InvalidArgumentException` from `send()`. The file exists but PHP cannot read it; check the file permissions.

### no such table: signer_documents, or "Base table or view not found"

The migrations have not run. Run `php artisan migrate`. The migrations are loaded from the package, there is nothing to publish.

### No mail arrives

The invitation is sent by the mailer of your application, during `send()`. With `MAIL_MAILER=log`, the default of a new Laravel application, the mail is in `storage/logs/laravel.log`. Set the `MAIL_*` values in `.env` for real mail and run `php artisan config:clear`. When the mailer throws for one signer, the document and the signers before that one exist already; do not call `send()` again, it would create a second document. Send the invitation again instead, see [When the link expires](signing.md#when-the-link-expires).

### send() is slow with several signers

Every invitation is mailed during the call, one after the other. Call `send()` from a [queued job](https://laravel.com/docs/queues) when that takes too long for a web request.

## The signing link

### 403 Invalid signature.

The signed link is not valid for this request. The causes, most common first:

1. **The link expired.** It is valid for `link_expires_after_hours` hours (72). After that the signing page, the PDF and the submit all answer this 403. Send the invitation again and record it, see [When the link expires](signing.md#when-the-link-expires).
2. **The link was opened without its query string**, for example `route('signer.show', $signer)` in your own code. Only the link from the mail, with `expires` and `signature`, opens the page.
3. **The address differs from the one the link was made for.** Laravel signs the full URL. A link made in tinker or in a job uses `APP_URL`; when that is `http://localhost` and the signer opens `https://example.com`, the signature does not match. Set `APP_URL` correctly and run `php artisan config:clear`. Behind a load balancer or proxy that ends HTTPS, configure [trusted proxies](https://laravel.com/docs/requests#configuring-trusted-proxies), or Laravel sees `http` where the link says `https`.
4. **You sent a new invitation without recording it.** The new link opens the page, but the PDF stays empty and the submit answers 403, because the server counts from the newest `invitation_sent` audit event. Log that event when you resend.

### The signing page opens but the PDF stays empty

The PDF is loaded from the route `signer.pdf`. It answers 403 when the link has expired (see above) and an error when the original is no longer on the disk. Check that `SIGNER_DISK` and `SIGNER_STORAGE_PATH` still point to where the document was stored.

### 403 This document has already been signed.

The signer posted a signature a second time. A signer signs once; the signing page redirects a signer who already signed to the confirmation page.

### 403 This document can no longer be signed.

The document has the status `Cancelled` or `Completed`. The package never sets `Cancelled` itself, so your own code did.

### 404 on the confirmation page

`signer.done` answers 404 for a signer who has not signed yet.

### 419 Page Expired when submitting

The signing routes use the `web` middleware group, so the form carries a CSRF token, a hidden value Laravel uses to check that the form came from your own site. The session ended between opening the page and submitting. Reload the signing page and sign again. When you publish and change the view, keep `@csrf` in the form.

## Signing

### The signature could not be read. Please draw it again.

The validation message on the signing page. The field `signature` has to be a `data:image/png;base64,…` string that decodes to a real PNG of at most 5000 × 5000 pixels and `signature_max_kilobytes` (512), not 16 bit and not interlaced. The signature pad of the package always sends that. With a signing page of your own, send `canvas.toDataURL('image/png')`, and raise `SIGNER_SIGNATURE_MAX_KILOBYTES` for a very large canvas.

Called directly, `SignatureProcessor::sign()` throws an `InvalidArgumentException` instead, with one of these messages: `Signature must be a base64 encoded PNG data URL.`, `Signature data URL contains invalid base64 data.`, `Signature image is larger than <n> kilobytes.`, `Signature image is not a PNG file.`, `Signature image has unusable dimensions.` or `Signature image is a PNG variant that cannot be placed on a PDF.`

### Your signature could not be processed. Please try again.

Storing or stamping failed and everything was rolled back; the signer can submit again. The real cause is in your log or error tracker as `Darvis\Signer\Exceptions\SigningFailed` with the message `Signing failed for signer [<uuid>] and was rolled back: <cause>`. When it keeps happening for the last signer of a document, the cause is almost always one of the next three entries.

### This PDF document probably uses a compression technique

The full message comes from FPDI: `This PDF document probably uses a compression technique which is not supported by the free parser shipped with FPDI.` The package stamps with the free FPDI parser, which cannot read every PDF. Export or print the document to PDF again with another program or an older PDF version, and check it before sending, see [Check the page count before you send](signing.md#check-the-page-count-before-you-send).

### File [<path>] is missing from the signer disk.

A `RuntimeException` while stamping. The original or a signature image is no longer on the disk. This happens when `SIGNER_DISK` or `SIGNER_STORAGE_PATH` changed after documents were sent, or when files were removed by hand. Related messages from the stamper are `Signer [<uuid>] has no captured signature.`, `Unable to read the size of page <n>.` and `Unable to write temporary file for stamping.`; the last one means PHP cannot write to the system temp directory.

### Signature image could not be written to [<path>].

The disk refused the write. Check the permissions of the disk root, or the credentials of a cloud disk.

### The document is completed but a signature is missing on the PDF

The signer has a `page` beyond the last page of the PDF. That is not an error: the document completes without that signature. Check the page count before sending, see [Check the page count before you send](signing.md#check-the-page-count-before-you-send). A signature that is only partly visible has `x`, `y` or `width` values that run off the page.

### Every signer has signed but the document stays Pending

This can only be left over from version 1.0, where a failed stamp did not roll back. Since 1.1.0 it cannot happen. Fix the cause and finish the document by hand:

```php
use Darvis\Signer\Enums\DocumentStatus;
use Darvis\Signer\Events\DocumentCompleted;
use Darvis\Signer\Services\AuditLogger;
use Darvis\Signer\Services\SignatureStamper;

$document->load('signers');

$document->update([
    'signed_path' => app(SignatureStamper::class)->stamp($document),
    'status' => DocumentStatus::Completed,
    'completed_at' => now(),
]);

app(AuditLogger::class)->log($document, 'document_completed');
event(new DocumentCompleted($document));
```

A document with a signer you marked `Declined` also stays `Pending`: `isFullySigned()` waits for every signer.

## The portal

### /portal answers 404

The portal routes are not registered. Check that `SIGNER_PORTAL_ENABLED` is not `false`, that `portal.prefix` in a published `config/signer.php` is what you expect, and clear the caches: `php artisan config:clear` and `php artisan route:clear`. The prefixes and `portal.enabled` are read when the routes are registered, so a cached config or cached routes keep the old value.

### These credentials do not match our records.

The email address and password do not match a user of the guard in `portal.guard`. The portal logs in against your application's own users; create one as shown in [Installation](installation.md#step-4-create-the-first-portal-user).

### 429 Too Many Requests on the login

The login allows five attempts a minute for an email address from one IP address. Wait a minute. To change the limit, register your own `signer-portal-login` rate limiter, see [The login is rate limited](portal.md#the-login-is-rate-limited).

### The login fails with an error that the method attempt does not exist

`portal.guard` points to a guard without a session login, such as a token guard for an API. Use a session guard such as `web`.

### Session store not set on request.

`portal.middleware` or `route_middleware` no longer contains `web`. The login, the CSRF check and the flash messages need the session; put `web` back as the first entry.

### Everybody who can log in sees all documents

That is how the portal works: it has no roles. Restrict it with your own middleware or turn it off, see [Who can log in and what they see](portal.md#who-can-log-in-and-what-they-see).

## Configuration and views

### A changed value in config/signer.php or .env has no effect

The config is cached. Run `php artisan config:clear`. For `route_prefix`, `route_middleware` and the `portal` keys also run `php artisan route:clear`.

### The signing page or the portal looks different from the documentation

You published the views with `--tag=signer-views`, and published views win over the ones in the package. Compare `resources/views/vendor/signer` with `vendor/darvis/laravel-document-sign/resources/views`, or delete the published copy of a view you did not change.

### The texts are English, I want Dutch

Set the locale of your application to `nl`; in Laravel 11 and newer that is `APP_LOCALE=nl` in `.env`. The mail and the pages use the locale of the request; there is no locale per signer.
