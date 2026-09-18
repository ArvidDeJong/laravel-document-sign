## darvis/laravel-document-sign

This package signs PDF documents with a DocuSign like flow: a document is stored, every signer gets a mail with a temporary signed link, signs on a canvas, and once everyone has signed the package stamps the signatures on the PDF and records an audit trail. It also ships an optional portal (login, customers, documents) under `/portal`.

- Config lives under the key `signer` (file `config/signer.php`). Read it through `Darvis\Signer\Support\SignerConfig` (`disk()`, `storagePath()`, `linkExpiresAfterHours()`, `portalGuard()`, …), never through `config('signer.…')` in code that extends the package.
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

- `send()` stores the original under `storage_path/originals/`, creates the signers and mails each one a `SignatureRequested` notification with a temporary signed URL to the route `signer.show`. The link expires after `link_expires_after_hours`; the signing page route `signer.show` uses the `signed` middleware, so a tampered or expired link is a 403. The `signer.pdf`, `signer.store` and `signer.done` routes are keyed on the signer uuid only.
- A signer signs once. `SignatureProcessor::sign()` stores the PNG, marks the signer `SignerStatus::Signed`, dispatches `Darvis\Signer\Events\SignerSigned`, and when every signer is done stamps the PDF with `SignatureStamper` (FPDI on top of FPDF) and dispatches `Darvis\Signer\Events\DocumentCompleted`. Listen for those events to mail the final PDF; don't poll the status.
- Signature data arrives as a `data:image/png;base64,…` URL from the canvas; anything else is rejected. The package needs the GD extension for it.
- Files never leave the configured disk: originals, signature images and signed PDFs are read and written through `Storage::disk()`, and the portal streams downloads from there. Don't build paths to `storage/app` by hand.
- Models: `Document` (uuid, title, status, original_path, signed_path), `Signer` (uuid, name, email, page, x, y, width, status, signature_path), `Customer` with `Contact`s, and `AuditEvent`. Tables are prefixed `signer_`.
- The portal is on by default under `portal.prefix` with the `portal.guard` guard; any user of the host app can log in. Set `SIGNER_PORTAL_ENABLED=false` for the signing flow only. Route names start with `signer.portal.`; the signing routes are `signer.show`, `signer.pdf`, `signer.store` and `signer.done`.
- Texts go through `__()` with a Dutch translation in `lang/nl.json`; set the app locale to `nl` for a Dutch portal and signing page.
