## darvis/laravel-document-sign

This package signs PDF documents with a DocuSign like flow: a document is stored, every signer gets a mail with a temporary signed link, signs on a canvas, and once everyone has signed the package stamps the signatures on the PDF and records an audit trail. It also ships an optional portal (login, customers, documents) under `/portal`.

- Config lives under the key `signer` (file `config/signer.php`). Read it through `Darvis\Signer\Support\SignerConfig` (`disk()`, `storagePath()`, `linkExpiresAfterHours()`, `signatureMaxKilobytes()`, `portalGuard()`, …), never through `config('signer.…')` in code that extends the package.
- Start a flow through the facade and the builder; positions are percentages of the page, measured from the top left, so they work on any paper size:

@verbatim
<code-snippet name="Send a document for signing" lang="php">
use Darvis\Signer\Facades\Signer;

$document = Signer::document(storage_path('contracts/contract.pdf'))
    ->title('Freelance agreement')
    ->forCustomer($customer)                     // optional Darvis\Signer\Models\Customer
    ->addSigner('Alice Jansen', 'alice@example.com', page: 1, x: 10, y: 80)
    ->addSigner('Bob de Vries', 'bob@example.com', page: 1, x: 55, y: 80, width: 25)
    ->send();

$document->status;          // Darvis\Signer\Enums\DocumentStatus: Draft, Pending, Completed, Cancelled
$document->isFullySigned();
$document->signed_path;     // on the configured disk once completed
$document->auditEvents;     // document_created, invitation_sent, document_signed, document_completed
</code-snippet>
@endverbatim

- `send()` stores the original under `storage_path/originals/`, creates the signers and mails each one a `SignatureRequested` notification with a temporary signed URL to the route `signer.show`. The link expires after `link_expires_after_hours`; the signing page route `signer.show` uses the `signed` middleware, so a tampered or expired link is a 403. `signer.pdf` and `signer.store` enforce the same expiry on the server, counted from the newest `invitation_sent` audit event of that signer (or the signer's `created_at` without one), and answer with the same `InvalidSignatureException` 403.
- To send an invitation again, call `$signer->notify(new SignatureRequested($signer))` and `app(AuditLogger::class)->log($signer->document, 'invitation_sent', $signer)`. Without the audit event the new link opens the signing page, but the PDF and the submit stay closed.
- A signer signs once, and `signer.store` refuses a document that is `Cancelled` or `Completed`. `SignatureProcessor::sign()` runs in one database transaction with row locks: it stores the PNG, marks the signer `SignerStatus::Signed`, and when every signer is done stamps the PDF with `SignatureStamper` (FPDI on top of FPDF). Only after the commit it dispatches `Darvis\Signer\Events\SignerSigned` and `Darvis\Signer\Events\DocumentCompleted`. Listen for those events to mail the final PDF; don't poll the status.
- When storing or stamping throws, everything is rolled back, the written files are removed, `Darvis\Signer\Exceptions\SigningFailed` is reported and the signer is sent back with an error to try again. Don't mark a signer `Signed` by hand to work around it; fix the cause and let the signer submit again.
- Signature data arrives as a `data:image/png;base64,…` URL from the canvas. It must decode strictly to a real PNG of at most 5000 × 5000 pixels and `signature_max_kilobytes` (512); anything else is a validation error on `signature`. `composer.json` requires the GD extension, but the code in `src/` calls no GD function: the PNG is checked with `getimagesizefromstring()` and placed by FPDF, which reads PNG files with zlib.
- Use `Darvis\Signer\Support\DownloadName::forTitle($document->title)` for a download file name; a raw title with a `/` or `\` makes the response throw.
- Originals, signature images and signed PDFs are stored on the configured disk and read and written through `Storage::disk()`; the portal streams downloads from there, and the stamper only copies them to the system temp directory while it works. Don't build paths to `storage/app` by hand.
- Only the `document_signed` audit event stores the IP address and user agent; `document_created`, `invitation_sent` and `document_completed` are logged without the request.
- A signer `page` beyond the last page of the PDF is skipped without an error and the document completes without that signature. Check `(new \setasign\Fpdi\Fpdi)->setSourceFile($path)`, which returns the page count, before `send()`.
- `Signer::document($path)` throws `InvalidArgumentException` (`PDF file not found at [<path>].`) right away for a path that is not a local file; `send()` only throws for a missing signer or an unreadable file.
- Models: `Document` (uuid, title, status, original_path, signed_path), `Signer` (uuid, name, email, page, x, y, width, status, signature_path), `Customer` with `Contact`s, and `AuditEvent`. Tables are prefixed `signer_`.
- The portal is on by default under `portal.prefix` with the `portal.guard` guard; any user of the host app can log in and sees and can delete every customer and document, because the portal has no roles; restrict it with middleware in `portal.middleware`. The login is limited to five attempts a minute per email address and IP address through the named rate limiter `signer-portal-login`. Set `SIGNER_PORTAL_ENABLED=false` for the signing flow only. Route names start with `signer.portal.`; the signing routes are `signer.show`, `signer.pdf`, `signer.store` and `signer.done`.
- Texts go through `__()` with a Dutch translation in `lang/nl.json`; set the app locale to `nl` for a Dutch portal and signing page.
