---
title: Signing flow
description: "Send a PDF for signing with the fluent builder, place signatures by percentage, what signers see, how the link expires, and how to read the status and download the signed PDF."
nav_order: 3
---

# Signing flow

## Sending a document

```php
use Darvis\Signer\Facades\Signer;

$document = Signer::document(storage_path('contracts/contract.pdf'))
    ->title('Freelance agreement')
    ->forCustomer($customer)   // optional, a Darvis\Signer\Models\Customer
    ->addSigner('Alice Jansen', 'alice@example.com', page: 1, x: 10, y: 80)
    ->addSigner('Bob de Vries', 'bob@example.com', page: 1, x: 55, y: 80, width: 25)
    ->send();
```

`send()` copies the PDF to the configured disk under `storage_path/originals/`, creates a `Document` with status `Pending` and a `Signer` per invitation, and mails every signer a `SignatureRequested` notification with their personal signing link. It throws an `InvalidArgumentException` when the PDF path does not exist or no signer was added.

## Positions

A signature position is given in percentages of the page, measured from the top left corner, so the same numbers work on A4, Letter and any other size. `page` is the page number (1 based), `x` and `y` the top left corner of the signature, and `width` its width in percent of the page width; it falls back to `default_signature_width` from the config. The height follows from the aspect ratio of the drawn signature.

## What a signer sees

The mail links to the signing page under the `sign` prefix. The link is a temporary signed URL that expires after `link_expires_after_hours`; a tampered or expired link answers with a 403. On the page the signer sees the PDF, draws a signature on a canvas and confirms. A signer who already signed is redirected to the confirmation page, and a second submission is refused with a 403.

## When the link expires

The expiry counts for the whole signing flow, not only for the page in the mail. `link_expires_after_hours` hours after the invitation was last sent, `signer.pdf` stops streaming the document and `signer.store` refuses a signature, both with the same 403 (`Illuminate\Routing\Exceptions\InvalidSignatureException`) the expired link itself gives. Render that exception in your application to show signers a friendly page.

"Last sent" is the newest `invitation_sent` event of that signer in the [audit trail](events-and-audit.md); a signer without one falls back to the moment the signer was created. Sending the invitation again therefore reopens the window, as long as you record it:

```php
use Darvis\Signer\Notifications\SignatureRequested;
use Darvis\Signer\Services\AuditLogger;

$signer->notify(new SignatureRequested($signer));
app(AuditLogger::class)->log($signer->document, 'invitation_sent', $signer);
```

`signer.store` also answers 403 for a document that is `Cancelled` or `Completed`, so setting `DocumentStatus::Cancelled` on a document is enough to stop further signatures.

## The signature

The signature travels as a `data:image/png;base64,…` URL from the canvas. The package checks the prefix, decodes the base64 strictly, and verifies that the result really is a PNG the stamper can place: at most 5000 × 5000 pixels, at most `signature_max_kilobytes` (512), not 16 bit and not interlaced. Anything else is a normal validation error on the `signature` field: the signer is sent back to the signing page with a message and can draw again.

## When signing fails

Storing the signature, marking the signer and stamping the PDF happen in one database transaction. When something throws on the way, for example a PDF that FPDI cannot parse, nothing is kept: the signer stays `Pending`, the files written so far are removed, the exception is reported to your application's exception handler as `Darvis\Signer\Exceptions\SigningFailed` (the cause is its previous exception), and the signer sees a message with the request to try again. No event is dispatched for a signature that was rolled back.

## Completion

When every signer has signed, the package stamps all signatures on the original with FPDI, page by page, stores the result under `storage_path/signed/` and marks the document `Completed`. See [Events & audit trail](events-and-audit.md) for the hooks.

## Reading the status

```php
$document->refresh();

$document->status;          // Darvis\Signer\Enums\DocumentStatus: Draft, Pending, Completed, Cancelled
$document->isFullySigned(); // true once every signer has signed
$document->signed_path;     // path of the final PDF on the configured disk, null until completed
$document->signers;         // each with status, signed_at and signature_path
$document->auditEvents;     // the full audit trail
```

Each signer has a `Darvis\Signer\Enums\SignerStatus`: `Pending`, `Signed` or `Declined`.

## Downloading the files

Files never leave the configured disk. Read them through `Storage::disk(config('signer.disk'))` in your own code, or through the portal, which streams the original and the signed PDF from there.

```php
use Darvis\Signer\Support\DownloadName;
use Illuminate\Support\Facades\Storage;

return Storage::disk(config('signer.disk'))->download(
    $document->signed_path,
    DownloadName::forTitle($document->title),
);
```

`Darvis\Signer\Support\DownloadName::forTitle()` is what the package uses itself: it replaces `/` and `\` by a dash, drops control characters and falls back to `document.pdf`, so a title such as `Contract 2026/09` downloads as `Contract 2026-09.pdf`.

## Routes

| Name | Method | Purpose |
| --- | --- | --- |
| `signer.show` | GET | The signing page; carries the `signed` middleware |
| `signer.pdf` | GET | Streams the original PDF for the signing page; 403 once the link has expired |
| `signer.store` | POST | Stores the drawn signature; 403 once the link has expired, for a signer who already signed and for a cancelled or completed document |
| `signer.done` | GET | Confirmation after signing |

The prefix and middleware come from `route_prefix` and `route_middleware`.
