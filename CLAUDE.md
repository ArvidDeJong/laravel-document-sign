# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository. The conventions shared by every darvis package (language, releases, CI, docs site, Boost guidelines, public API policy) are in [../CLAUDE.md](../CLAUDE.md); this file only holds what is specific to this package.

## Package overview

`darvis/laravel-document-sign` is a Laravel package (PHP 8.2+, Laravel 11/12/13, GD extension) that signs PDF documents with a DocuSign like flow: store a PDF, invite signers by mail, let them draw a signature on a signing page, stamp every signature on the PDF and keep an audit trail. It ships an optional portal (login, dashboard, customers, documents) so a fresh Laravel install works as a standalone signing system. Host apps consume it via Composer; this repo only contains the library.

- Namespace: `Darvis\Signer\` → `src/`
- Service provider auto-registered via `extra.laravel.providers` in [composer.json](composer.json); the `Signer` facade is aliased too
- Config key: `signer`, file in [config/signer.php](config/signer.php); view namespace `signer`; translations are JSON in `lang/`

## Commands

```bash
composer test                 # Pest suite
vendor/bin/pest --filter "stamps"
composer lint                 # Pint (check only); composer format fixes
composer analyse              # Larastan, level 8
```

## Architecture

- [SignerConfig](src/Support/SignerConfig.php) is the only place that reads the package config; every controller, service, middleware and route file asks it. Never call `config('signer.…')` elsewhere.
- The flow is `Signer` facade → [SignerManager](src/Services/SignerManager.php) → [DocumentBuilder](src/Services/DocumentBuilder.php) (`title()`, `forCustomer()`, `addSigner()`, `send()`). `send()` stores the original on the configured disk, creates the `Document` and its `Signer`s and mails each signer a [SignatureRequested](src/Notifications/SignatureRequested.php) notification with a temporary signed URL to `signer.show`. The notification resolves `SignerConfig` on use, not in the constructor, so a queued notification serialises only the signer.
- Signing goes through [SignController](src/Http/Controllers/SignController.php) and [SignatureProcessor](src/Services/SignatureProcessor.php): the canvas posts a `data:image/png;base64,…` URL, the PNG is stored under `storage_path/signatures/`, the signer becomes `SignerStatus::Signed` and `Events\SignerSigned` fires. When `Document::isFullySigned()` the processor calls [SignatureStamper](src/Services/SignatureStamper.php), which rebuilds the PDF page by page with FPDI and places each signature with `Image()`, then `Events\DocumentCompleted` fires.
- Positions are percentages of the page (`x`, `y` from the top left, `width` of the page width, default from `default_signature_width`), so the same numbers work on any paper size. The stamper converts them per page with the imported template size.
- Only `signer.show` carries the `signed` middleware. `signer.pdf`, `signer.store` and `signer.done` are keyed on the signer uuid alone; a signer who already signed gets a 403 on `store` and a redirect on `show`.
- Every step is written to `signer_audit_events` by [AuditLogger](src/Services/AuditLogger.php) with IP address and user agent when a request is at hand: `document_created`, `invitation_sent`, `document_signed`, `document_completed`. Add a new step there, not in the controllers.
- The portal routes in [routes/portal.php](routes/portal.php) are only loaded when `portal.enabled` is true and are guarded by [AuthenticatePortal](src/Http/Middleware/AuthenticatePortal.php) against `portal.guard`, so any user of the host app can log in. The portal views use Tailwind from a CDN; there is no build step.
- Controllers extend `Illuminate\Routing\Controller`. Files are read and written through `Storage::disk()` only; nothing builds a path under `storage/app` by hand.

## Testing

- [TestCase](tests/TestCase.php) runs on in-memory SQLite with Testbench's `WithLaravelMigrations` trait (Laravel's own `users` table) and a fixture `User` model, because the portal logs in against the host app's users. Don't call `loadLaravelMigrations()` from `defineDatabaseMigrations()`: `RefreshDatabase` runs `migrate:fresh` afterwards and drops the table again. The helpers on the test case are public because Pest's global helper functions receive the test case and call them from outside the class. `createSamplePdf()` builds a two page PDF with FPDF and `signatureDataUrl()` a small PNG with GD, so no fixtures live on disk.
- Tests fake the configured disk with `Storage::fake(config('signer.disk'))`; reading the config directly is fine in tests.

## Conventions

- Keep the public API compatible within 1.x: the facade and `DocumentBuilder` methods, the models and enums, the events, the route names, the config keys and the view names.
- Config keys stay in alphabetical order, in the file and in `SignerConfig`.
- End user texts go through `__()`; add every new string to `lang/nl.json`.
