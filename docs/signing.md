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

The signature travels as a `data:image/png;base64,…` URL from the canvas. Anything else is rejected by validation.

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
use Illuminate\Support\Facades\Storage;

return Storage::disk(config('signer.disk'))->download($document->signed_path, $document->title.'.pdf');
```

## Routes

| Name | Method | Purpose |
| --- | --- | --- |
| `signer.show` | GET | The signing page; carries the `signed` middleware |
| `signer.pdf` | GET | Streams the original PDF for the signing page |
| `signer.store` | POST | Stores the drawn signature |
| `signer.done` | GET | Confirmation after signing |

The prefix and middleware come from `route_prefix` and `route_middleware`.
