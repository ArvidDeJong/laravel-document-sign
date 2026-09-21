---
name: laravel-document-sign-development
description: Work with darvis/laravel-document-sign. Use it to send a PDF for signing with the fluent builder, place signatures by percentage, react to the signing events, resend an invitation, recover a document whose stamping failed, read the audit trail, run with or without the portal, and test the signing flow without sending mail.
---

# darvis/laravel-document-sign development

## When to use this skill

Use this skill when code sends a PDF for signing in an application that has `darvis/laravel-document-sign` installed, when you listen for `SignerSigned` or `DocumentCompleted`, when a document stays `Pending` although everyone signed, when a signing link answers with a 403 or a 500, when you build your own screens on the `signer_` tables, or when you write tests around the signing flow.

## How the flow runs

1. `Signer::document($pdfPath)` returns a `Darvis\Signer\Services\DocumentBuilder`. The path is a local file path, checked with `is_file()` right away; it is not a path on a Laravel disk.
2. `title()`, `forCustomer()` and `addSigner()` only collect values. `addSigner(string $name, string $email, int $page = 1, float $x = 10.0, float $y = 80.0, ?float $width = null)` validates nothing.
3. `send()` copies the PDF to `<storage_path>/originals/<uniqid>.pdf` on the configured disk, creates a `Document` with `DocumentStatus::Pending` and one `Signer` per `addSigner()` call, writes the audit event `document_created`, and then, per signer, sends a `SignatureRequested` mail notification and writes `invitation_sent`. It returns the `Document` with `signers` loaded.
4. The mail holds a temporary signed URL to the route `signer.show`, valid for `link_expires_after_hours` hours (72). The signing page shows the PDF through `signer.pdf` and posts the canvas as a `data:image/png;base64,…` string in the field `signature` to `signer.store`.
5. `SignatureProcessor::sign()` stores the PNG at `<storage_path>/signatures/<signer uuid>.png`, sets the signer to `SignerStatus::Signed` with `signed_at`, writes `document_signed` with the IP address and user agent, and dispatches `Darvis\Signer\Events\SignerSigned`.
6. When `Document::isFullySigned()` is true, `SignatureStamper::stamp()` rebuilds the PDF page by page with FPDI, places every signature and stores `<storage_path>/signed/<document uuid>.pdf`. The document gets `signed_path`, `DocumentStatus::Completed` and `completed_at`, the audit event `document_completed` is written and `Darvis\Signer\Events\DocumentCompleted` is dispatched.

Steps 5 and 6 run inside the signer's own HTTP request, and nothing is wrapped in a database transaction.

| Situation | What you get | State left behind |
| --- | --- | --- |
| `Signer::document()` with a path that is not a file | `InvalidArgumentException`: `PDF file not found at [<path>].` | nothing |
| `send()` without `addSigner()` | `InvalidArgumentException`: `Add at least one signer before sending.` | nothing |
| The PDF cannot be read in `send()` | `InvalidArgumentException`: `PDF file at [<path>] could not be read.` | nothing |
| The mailer fails for a signer in `send()` | the mailer's own exception | the document, all signers, the stored original and `document_created` exist; signers before the failing one were mailed |
| `signer.show` with an expired, missing or tampered signature | 403 from the `signed` middleware | nothing |
| `signer.show` for a signer who already signed | redirect to `signer.done` | nothing |
| `signer.store` for a signer who already signed | 403 `This document has already been signed.` | nothing |
| `signature` does not start with `data:image/png;base64,` | validation error on `signature`, redirect back | signer stays `Pending` |
| `signature` has the prefix but invalid base64 | `InvalidArgumentException`: `Signature data URL contains invalid base64 data.` (a 500) | signer stays `Pending` |
| `SignatureProcessor::sign()` called with another format | `InvalidArgumentException`: `Signature must be a base64 encoded PNG data URL.` | signer stays `Pending` |
| `signer.done` for a signer who did not sign | 404 | nothing |
| A file is gone from the disk while stamping | `RuntimeException`: `File [<path>] is missing from the signer disk.` | the last signer is `Signed`, the document stays `Pending` |
| FPDI cannot parse the original | `setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException`: `This PDF document probably uses a compression technique which is not supported by the free parser shipped with FPDI. …` | the last signer is `Signed`, the document stays `Pending` |
| The stored signature is not a PNG that FPDF can read | `Exception`: `FPDF error: Not a PNG file: <temp file>` | the last signer is `Signed`, the document stays `Pending` |

The other stamping messages are `Signer [<uuid>] has no captured signature.`, `Unable to read the size of page <n>.` and `Unable to write temporary file for stamping.`, all `RuntimeException`.

The package never writes to the Laravel log. What happened is in `signer_audit_events`; an exception reaches the host app's exception handler like any other.

## Sending a document

```php
use Darvis\Signer\Facades\Signer;

$document = Signer::document(storage_path('app/contracts/contract.pdf'))
    ->title('Freelance agreement')              // default title is 'Document'
    ->forCustomer($customer)                    // optional Darvis\Signer\Models\Customer, null is allowed
    ->addSigner('Alice Jansen', 'alice@example.com', page: 1, x: 10, y: 80)
    ->addSigner('Bob de Vries', 'bob@example.com', page: 2, x: 55, y: 80, width: 25)
    ->send();
```

`x` and `y` are the top left corner of the signature in percent of the page, measured from the top left; `width` is percent of the page width and falls back to `default_signature_width` (20.0). The height follows the aspect ratio of the drawn image.

A PDF that lives on a disk such as S3 has to be written to a local temporary file first, because the builder reads it with `is_file()` and `file_get_contents()`.

## Check the PDF before you send it

The free FPDI parser cannot read every PDF, and a `page` beyond the last page is not an error: the document completes and that signature is simply not on it. Both only show up after the last signer has signed, so check up front:

```php
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParserException;

try {
    $pageCount = (new Fpdi)->setSourceFile($path);
} catch (PdfParserException $e) {
    // Not a PDF the package can stamp: ask for another export of the file.
}

abort_if($page > $pageCount, 422);
```

## Reacting to a signature

```php
use Darvis\Signer\Events\DocumentCompleted;
use Darvis\Signer\Events\SignerSigned;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

Event::listen(function (SignerSigned $event): void {
    // $event->signer, with $event->signer->document
});

Event::listen(function (DocumentCompleted $event): void {
    $pdf = Storage::disk(app(SignerConfig::class)->disk())->get($event->document->signed_path);
});
```

Both events are dispatched synchronously in the request of the person who signs. A slow listener makes them wait and a listener that throws gives them a 500 although the signature is stored, so put real work in a queued listener.

## Resending an invitation

There is no resend method. Send the notification again and record it yourself; the new link gets a fresh expiry:

```php
use Darvis\Signer\Notifications\SignatureRequested;
use Darvis\Signer\Services\AuditLogger;

$signer->notify(new SignatureRequested($signer));
app(AuditLogger::class)->log($signer->document, 'invitation_sent', $signer);
```

`AuditLogger::log(Document $document, string $event, ?Signer $signer = null, ?Request $request = null, array $context = [])` is also the way to add your own steps to the trail. IP address and user agent are only stored when you pass the request.

## Recovering a document that was not stamped

When stamping throws, the last signer is already `Signed`, so they cannot submit again (403) and the document stays `Pending`. Fix the cause, then finish it by hand, which is what the processor does:

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

Find candidates with `Document::where('status', DocumentStatus::Pending)->get()->filter->isFullySigned()`.

## Pitfalls

- The expiry only guards `signer.show`. `signer.pdf`, `signer.store` and `signer.done` are keyed on the signer uuid alone, so someone who has the uuid from an old link can still read the original and post a signature after the link expired. Treat the uuid as a secret, and add your own middleware through `route_middleware` when that is not enough.
- `send()` mails synchronously (`SignatureRequested` is not queued) and is not transactional. Call it from a queued job when there are several signers, and do not retry a failed `send()` blindly: it creates a second document.
- A title with a `/` or `\` in it makes `signer.pdf` and the portal download answer with a 500, because the title becomes the download file name. Strip those characters before `title()`.
- `DocumentStatus::Draft`, `DocumentStatus::Cancelled` and `SignerStatus::Declined` exist, but the package never sets them and `signer.store` does not look at them. `isFullySigned()` counts every signer whose status is not `signed`, so a signer you mark `Declined` keeps the document `Pending` for ever. Cancelling is up to the host app, including blocking the signing routes for it.
- `isFullySigned()` runs a query on every call and returns `true` for a document without signers.
- `addSigner()` accepts any number. Keep `x`, `y` and `x + width` within 0 to 100, or the signature lands partly off the page. The columns are `decimal(5, 2)`.
- Deleting a `Document` with Eloquent removes its signers and audit events through the foreign keys, but leaves the files on the disk. Only the portal's delete action removes the original, the signatures and the signed PDF. Deleting a `Customer` sets `customer_id` on its documents to null and removes its contacts.
- The mail and the signing page use the application locale of that request; there is no locale per signer. The strings go through `__()` with a Dutch translation in `lang/nl.json`.
- The portal is on by default, at `/portal`, and lets every user of the `portal.guard` guard see every customer and document. There is no role check and no `throttle` on the login route. Add middleware through `portal.middleware` (it covers the login route too), or turn the portal off with `SIGNER_PORTAL_ENABLED=false`.
- The migrations are loaded from the package and are not publishable; only `signer-config` and `signer-views` are publish tags. The tables are `signer_customers`, `signer_contacts`, `signer_documents`, `signer_signers` and `signer_audit_events`.
- The signing routes (`signer.show`, `signer.pdf`, `signer.store`, `signer.done`) bind the signer on `uuid`; the portal routes (`signer.portal.*`) bind on `id`.

## Settings

Read them through `Darvis\Signer\Support\SignerConfig` (a singleton), not through the config helper:

| Accessor | Key | Env | Default |
| --- | --- | --- | --- |
| `defaultSignatureWidth()` | `default_signature_width` | | `20.0` |
| `disk()` | `disk` | `SIGNER_DISK` | `local` |
| `linkExpiresAfterHours()` | `link_expires_after_hours` | `SIGNER_LINK_EXPIRES_AFTER_HOURS` | `72` |
| `portalEnabled()` | `portal.enabled` | `SIGNER_PORTAL_ENABLED` | `true` |
| `portalGuard()` | `portal.guard` | | `web` |
| `portalMiddleware()` | `portal.middleware` | | `['web']` |
| `portalPrefix()` | `portal.prefix` | | `portal` |
| `routeMiddleware()` | `route_middleware` | | `['web']` |
| `routePrefix()` | `route_prefix` | | `sign` |
| `storagePath()` | `storage_path` | `SIGNER_STORAGE_PATH` | `signer` |

The prefixes, the middleware and `portal.enabled` are read when the routes are registered, so changing them at runtime has no effect. `composer.json` requires PHP 8.2+, the GD extension, `setasign/fpdf` and `setasign/fpdi`.

## Testing

Fake the disk and the notifications; no mail goes out and nothing is written to the real disk. FPDF ships with the package, so a test can build its own PDF.

```php
use Darvis\Signer\Enums\DocumentStatus;
use Darvis\Signer\Events\DocumentCompleted;
use Darvis\Signer\Facades\Signer;
use Darvis\Signer\Notifications\SignatureRequested;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('completes the document when the signer signs', function () {
    Storage::fake(app(SignerConfig::class)->disk());
    Notification::fake();
    Event::fake([DocumentCompleted::class]);

    $pdf = new FPDF;
    $pdf->AddPage();
    $path = tempnam(sys_get_temp_dir(), 'contract');
    $pdf->Output('F', $path);

    $document = Signer::document($path)->addSigner('Alice', 'alice@example.com')->send();
    $signer = $document->signers->first();

    Notification::assertSentTo($signer, SignatureRequested::class);

    $image = imagecreatetruecolor(120, 40);
    imageline($image, 10, 30, 110, 10, imagecolorallocate($image, 255, 255, 255));
    ob_start();
    imagepng($image);
    $signature = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());

    $this->post(route('signer.store', $signer), ['signature' => $signature])
        ->assertRedirect(route('signer.done', $signer));

    expect($document->refresh()->status)->toBe(DocumentStatus::Completed);
    Storage::disk(app(SignerConfig::class)->disk())->assertExists($document->signed_path);
    Event::assertDispatched(DocumentCompleted::class);
});
```

- Always pass the event classes to `Event::fake([...])`. A bare `Event::fake()` also silences the model events that fill `uuid`, and `send()` then fails with `NOT NULL constraint failed: signer_documents.uuid`.
- The signature must be a real PNG. Any valid base64 string is stored and marks the signer `Signed`; it only breaks once the last signer signs and the stamping reads the file (`FPDF error: Not a PNG file`).
- Open the signing page with a link you build yourself, `URL::temporarySignedRoute('signer.show', now()->addHour(), ['signer' => $signer->uuid])`; `route('signer.show', $signer)` without a signature is a 403. Use `$this->travel(73)->hours()` to assert that a link expired.
- The portal logs in against the host app's users: `$this->actingAs($user)->get(route('signer.portal.dashboard'))`. A guest is redirected to `signer.portal.login`.
- Set other values inside the test with `config()->set(...)` before the code under test runs; route prefixes and `portal.enabled` cannot be changed after boot.
