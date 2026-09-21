---
name: laravel-document-sign-development
description: Work with darvis/laravel-document-sign. Use it to send a PDF for signing with the fluent builder, place signatures by percentage, react to the signing events, resend an invitation so the signing window reopens, handle a signature that failed and was rolled back, read the audit trail, run with or without the portal, and test the signing flow without sending mail.
---

# darvis/laravel-document-sign development

## When to use this skill

Use this skill when code sends a PDF for signing in an application that has `darvis/laravel-document-sign` installed, when you listen for `SignerSigned` or `DocumentCompleted`, when a document stays `Pending` although everyone signed, when a signing link, the PDF or the submit answers with a 403, when a signer is told to try again, when you build your own screens on the `signer_` tables, or when you write tests around the signing flow.

## How the flow runs

1. `Signer::document($pdfPath)` returns a `Darvis\Signer\Services\DocumentBuilder`. The path is a local file path, checked with `is_file()` right away; it is not a path on a Laravel disk.
2. `title()`, `forCustomer()` and `addSigner()` only collect values. `addSigner(string $name, string $email, int $page = 1, float $x = 10.0, float $y = 80.0, ?float $width = null)` validates nothing.
3. `send()` copies the PDF to `<storage_path>/originals/<uniqid>.pdf` on the configured disk, creates a `Document` with `DocumentStatus::Pending` and one `Signer` per `addSigner()` call, writes the audit event `document_created`, and then, per signer, sends a `SignatureRequested` mail notification and writes `invitation_sent`. It returns the `Document` with `signers` loaded.
4. The mail holds a temporary signed URL to the route `signer.show`, valid for `link_expires_after_hours` hours (72). The signing page shows the PDF through `signer.pdf` and posts the canvas as a `data:image/png;base64,…` string in the field `signature` to `signer.store`. Both routes enforce the same expiry on the server: `Signer::invitationExpired($hours)` counts from `Signer::lastInvitedAt()`, the newest `invitation_sent` audit event of that signer, or the signer's `created_at` when the trail has none.
5. `signer.store` validates the signature with `Darvis\Signer\Rules\ValidSignature`: the prefix, strict base64, the PNG magic bytes, `getimagesizefromstring()` reporting `image/png`, at most 5000 × 5000 pixels, at most `signature_max_kilobytes` (512) of decoded data, and neither 16 bit nor interlaced, because FPDF cannot place those.
6. `SignatureProcessor::sign()` opens a database transaction, locks the document row and the signer row with `lockForUpdate()`, stores the PNG at `<storage_path>/signatures/<signer uuid>.png`, sets the signer to `SignerStatus::Signed` with `signed_at` and writes `document_signed` with the IP address and user agent (the user agent cut to 255 characters).
7. When `Document::isFullySigned()` is true, still inside the transaction, `SignatureStamper::stamp()` rebuilds the PDF page by page with FPDI, places every signature and stores `<storage_path>/signed/<document uuid>.pdf`. The document gets `signed_path`, `DocumentStatus::Completed` and `completed_at` and the audit event `document_completed` is written.
8. After the commit `Darvis\Signer\Events\SignerSigned` is dispatched, and `Darvis\Signer\Events\DocumentCompleted` when the document was completed.

Steps 5 to 8 run inside the signer's own HTTP request. When anything in steps 6 and 7 throws, the transaction is rolled back, the files written in this attempt are deleted, no event is dispatched and `Darvis\Signer\Exceptions\SigningFailed` is thrown with the cause as its previous exception. `SignController` reports it with `report()` and redirects back with an error on `signature`, so the signer can submit again.

| Situation | What you get | State left behind |
| --- | --- | --- |
| `Signer::document()` with a path that is not a file | `InvalidArgumentException`: `PDF file not found at [<path>].` | nothing |
| `send()` without `addSigner()` | `InvalidArgumentException`: `Add at least one signer before sending.` | nothing |
| The PDF cannot be read in `send()` | `InvalidArgumentException`: `PDF file at [<path>] could not be read.` | nothing |
| The mailer fails for a signer in `send()` | the mailer's own exception | the document, all signers, the stored original and `document_created` exist; signers before the failing one were mailed |
| `signer.show` with an expired, missing or tampered signature | 403 from the `signed` middleware | nothing |
| `signer.pdf` or `signer.store` after the link expired | 403, `Illuminate\Routing\Exceptions\InvalidSignatureException`, the same as `signer.show` | nothing |
| `signer.store` for a document that is `Cancelled` or `Completed` | 403 `This document can no longer be signed.` | nothing |
| `signer.show` for a signer who already signed | redirect to `signer.done` | nothing |
| `signer.store` for a signer who already signed | 403 `This document has already been signed.` | nothing |
| `signature` is missing, not a string, without the `data:image/png;base64,` prefix, invalid base64, not a PNG, a 16 bit or interlaced PNG, larger than 5000 pixels on a side or above `signature_max_kilobytes` | validation error on `signature`: `The signature could not be read. Please draw it again.`, redirect back | signer stays `Pending`, nothing is stored |
| `SignatureProcessor::sign()` called directly with such a value | `InvalidArgumentException`, for example `Signature must be a base64 encoded PNG data URL.`, `Signature data URL contains invalid base64 data.` or `Signature image is not a PNG file.` | signer stays `Pending`, nothing is stored |
| `SignatureProcessor::sign()` for a signer who signed in the meantime (a double submit) | the signer is returned as it is, no second audit event and no events | nothing changes |
| `signer.done` for a signer who did not sign | 404 | nothing |
| A file is gone from the disk while stamping | `SigningFailed`, previous `RuntimeException`: `File [<path>] is missing from the signer disk.` | rolled back: the last signer is `Pending` again, the document stays `Pending` |
| FPDI cannot parse the original | `SigningFailed`, previous `setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException`: `This PDF document probably uses a compression technique which is not supported by the free parser shipped with FPDI. …` | rolled back, as above |
| A stored signature file was replaced by something FPDF cannot read | `SigningFailed`, previous `Exception`: `FPDF error: …` | rolled back, as above |

The other stamping messages are `Signer [<uuid>] has no captured signature.`, `Unable to read the size of page <n>.` and `Unable to write temporary file for stamping.`, all `RuntimeException` and all wrapped in `SigningFailed`.

In the web flow the signer does not get a 500 for a `SigningFailed`: they are redirected back with `Your signature could not be processed. Please try again.` and the exception goes to the host app's exception handler through `report()`. That is the only thing the package reports; what happened otherwise is in `signer_audit_events`. A signer whose last co-signer keeps failing will keep getting that message until the cause (usually the PDF) is fixed, so watch for `SigningFailed` in your error tracker.

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

Both events are dispatched synchronously in the request of the person who signs, after the transaction has been committed; a signature that was rolled back dispatches nothing. A slow listener makes them wait and a listener that throws gives them a 500 although the signature is stored, so put real work in a queued listener.

## Resending an invitation

There is no resend method. Send the notification again and record it yourself. The new link gets a fresh expiry, and the `invitation_sent` event is what reopens `signer.pdf` and `signer.store` for another `link_expires_after_hours` hours:

```php
use Darvis\Signer\Notifications\SignatureRequested;
use Darvis\Signer\Services\AuditLogger;

$signer->notify(new SignatureRequested($signer));
app(AuditLogger::class)->log($signer->document, 'invitation_sent', $signer);
```

Never leave the `invitation_sent` line out. Without it the new link opens the signing page, but the PDF does not load and the submit answers 403, because the server still counts from the first invitation.

`AuditLogger::log(Document $document, string $event, ?Signer $signer = null, ?Request $request = null, array $context = [])` is also the way to add your own steps to the trail. IP address and user agent are only stored when you pass the request.

## Recovering a document that was not stamped

Since 1.1.0 a failed stamp rolls the signature back, so the signer simply submits again once the cause is fixed and nothing has to be recovered. A document can still be stuck when it got that way before 1.1.0: every signer is `Signed` and the document is `Pending`. Fix the cause, then finish it by hand, which is what the processor does:

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

- The expiry is enforced on `signer.show` (the signed URL) and, on the server, on `signer.pdf` and `signer.store`, counted from the newest `invitation_sent` audit event of the signer. A link you build yourself with a longer lifetime than `link_expires_after_hours` opens the signing page, but the PDF and the submit close at the configured hours. Deleting audit events moves the start of the window back to the signer's `created_at`.
- `signer.done` is keyed on the signer uuid alone and has no expiry. It only shows the document title, the signer's name and the signing date, and only after that signer signed.
- A signer who opens the page just before the expiry and submits just after it gets a 403. Send the invitation again to let them in.
- `send()` mails synchronously (`SignatureRequested` is not queued) and is not transactional. Call it from a queued job when there are several signers, and do not retry a failed `send()` blindly: it creates a second document.
- The package builds download file names with `Darvis\Signer\Support\DownloadName::forTitle()`, which replaces `/` and `\`, drops control characters and falls back to `document.pdf`. Use it in your own download responses too; a raw title with a `/` or `\` makes Symfony's `makeDisposition()` throw.
- `DocumentStatus::Draft`, `DocumentStatus::Cancelled` and `SignerStatus::Declined` exist, but the package never sets them. `signer.store` refuses a document that is `Cancelled` or `Completed` with a 403, so setting `Cancelled` stops further signatures; `signer.show` and `signer.pdf` still answer for it until the link expires. `isFullySigned()` counts every signer whose status is not `signed`, so a signer you mark `Declined` keeps the document `Pending` for ever.
- `SignatureProcessor::sign()` opens its own transaction on the signer's connection. Called inside a transaction of your own it becomes a savepoint, and the events are dispatched before your outer commit.
- `isFullySigned()` runs a query on every call and returns `true` for a document without signers.
- `addSigner()` accepts any number. Keep `x`, `y` and `x + width` within 0 to 100, or the signature lands partly off the page. The columns are `decimal(5, 2)`.
- Deleting a `Document` with Eloquent removes its signers and audit events through the foreign keys, but leaves the files on the disk. Only the portal's delete action removes the original, the signatures and the signed PDF. Deleting a `Customer` sets `customer_id` on its documents to null and removes its contacts.
- The mail and the signing page use the application locale of that request; there is no locale per signer. The strings go through `__()` with a Dutch translation in `lang/nl.json`.
- The portal is on by default, at `/portal`, and lets every user of the `portal.guard` guard see every customer and document. There is no role check. Add middleware through `portal.middleware` (it covers the login route too), or turn the portal off with `SIGNER_PORTAL_ENABLED=false`.
- The portal login is limited to five attempts a minute per email address and IP address, through the named rate limiter `signer-portal-login` (`SignerServiceProvider::LOGIN_RATE_LIMITER`); the sixth attempt is a 429. Register `RateLimiter::for('signer-portal-login', …)` in a provider that boots after the package to change it. Tests that log in more than five times with one address need `$this->travel(61)->seconds()` or `RateLimiter::clear()`.
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
| `signatureMaxKilobytes()` | `signature_max_kilobytes` | `SIGNER_SIGNATURE_MAX_KILOBYTES` | `512` |
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
- The signature must be a real PNG; anything else is a validation error on `signature` (`assertSessionHasErrors('signature')`) and nothing is stored.
- Open the signing page with a link you build yourself, `URL::temporarySignedRoute('signer.show', now()->addHour(), ['signer' => $signer->uuid])`; `route('signer.show', $signer)` without a signature is a 403. Use `$this->travel(73)->hours()` to assert that a link expired: `signer.show`, `signer.pdf` and `signer.store` then all answer 403.
- To test a failed stamp, bind a `SignatureStamper` subclass whose `stamp()` throws with `$this->app->instance(SignatureStamper::class, $fake)`, post a signature and assert the redirect back with an error on `signature`, the signer still `Pending` and `Event::assertNotDispatched(SignerSigned::class)`.
- The portal logs in against the host app's users: `$this->actingAs($user)->get(route('signer.portal.dashboard'))`. A guest is redirected to `signer.portal.login`.
- Set other values inside the test with `config()->set(...)` before the code under test runs; route prefixes and `portal.enabled` cannot be changed after boot.
