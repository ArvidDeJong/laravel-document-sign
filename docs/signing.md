---
title: "Signing flow"
description: "How the signing flow works: the builder, positions in percent, checking the page count, link expiry, failed signatures, reading the status and downloads."
nav_order: 4
---

# Signing flow

## Sending a document

In a controller, a job or a command of your application; the [Quick start](quick-start.md) has a complete controller.

```php
use Darvis\Signer\Facades\Signer;

$document = Signer::document(storage_path('app/contracts/contract.pdf'))
    ->title('Freelance agreement')
    ->forCustomer($customer)   // optional, a Darvis\Signer\Models\Customer
    ->addSigner('Alice Jansen', 'alice@example.com', page: 1, x: 10, y: 80)
    ->addSigner('Bob de Vries', 'bob@example.com', page: 1, x: 55, y: 80, width: 25)
    ->send();
```

`Signer::document()` takes the path of a file on the server and throws an `InvalidArgumentException` (`PDF file not found at [<path>].`) right away when it is not a file. `title()` is optional, the default title is `Document`. `forCustomer()` is optional too.

`send()` copies the PDF to the configured disk under `storage_path/originals/`, creates a `Document` with status `Pending` and a `Signer` per `addSigner()` call, and mails every signer a `SignatureRequested` notification with their personal signing link. It throws an `InvalidArgumentException` (`Add at least one signer before sending.`) when no signer was added. The mails are sent during the call, not through the queue.

## Positions

A signature position is given in percentages of the page, measured from the top left corner, so the same numbers work on A4, Letter and any other size. `page` is the page number (1 based), `x` and `y` the top left corner of the signature, and `width` its width in percent of the page width; it falls back to `default_signature_width` from the config. The height follows from the aspect ratio of the drawn signature.

`addSigner(string $name, string $email, int $page = 1, float $x = 10.0, float $y = 80.0, ?float $width = null)` does not validate these numbers. Keep `x`, `y` and `x` plus `width` between 0 and 100, or the signature lands partly off the page.

## Check the page count before you send

A `page` beyond the last page of the PDF is not an error. The document completes, and the signature of that signer is not on it. Check the number of pages first with FPDI, which the package installs:

```php
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;

try {
    $pageCount = (new Fpdi)->setSourceFile($path);
} catch (PdfParserException $e) {
    // FPDI cannot read this PDF, so the package cannot stamp it either.
}

abort_if($page > $pageCount, 422, 'That page does not exist in this PDF.');
```

The same check catches a PDF that FPDI cannot parse, see [Troubleshooting](troubleshooting.md#this-pdf-document-probably-uses-a-compression-technique).

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

The original, the signature images and the signed PDF are stored on the configured disk. Read them through `Storage::disk(config('signer.disk'))` in your own code, or through the portal, which streams the original and the signed PDF from there.

In a controller method of your application that received the `Document`:

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
| `signer.done` | GET | Confirmation after signing; 404 for a signer who has not signed |

The prefix and middleware come from `route_prefix` and `route_middleware`. All four routes find the signer by its `uuid`.
