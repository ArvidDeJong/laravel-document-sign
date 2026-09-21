# Changelog

All notable changes to `darvis/laravel-document-sign` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed

Documentation only; nothing in the package changes.

- The installation page said the migrations create four tables and then listed five. They create five: `signer_customers`, `signer_contacts`, `signer_documents`, `signer_signers` and `signer_audit_events`.
- The signing page said `send()` throws for a PDF path that does not exist. `Signer::document($path)` throws that `InvalidArgumentException` (`PDF file not found at [<path>].`) right away; `send()` throws for a missing signer or an unreadable file.
- The reason given for the GD extension ("the captured signatures are PNG images", "the package needs the GD extension for it") was not true for the code. `composer.json` requires `ext-gd`, but `src/` calls no GD function: a signature is checked with `getimagesizefromstring()` and placed by FPDF, which reads PNG files with zlib. The docs, the Boost guideline, `CLAUDE.md` and `CONTRIBUTING.md` now say that.
- The audit trail was described as recording "the IP address and user agent when a request was at hand" for every step. Only `document_signed` stores them; `document_created`, `invitation_sent` and `document_completed` leave both columns empty.
- "Deleting a document through the portal removes its files" read as if deleting always does. Only the delete button in the portal removes the files; `$document->delete()` in your own code removes the signers and audit events and leaves the files on the disk.
- "Files never leave the configured disk" was too strong: the stamper copies the original and the signatures to the system temp directory while it works. The text now says the files are stored on the configured disk.
- The portal page and the home page now say plainly that every user who can log in sees and can delete every customer and document, with a middleware example to restrict it.
- A signer `page` beyond the last page is skipped without an error and the document completes without that signature. This is now a documented pitfall, with the FPDI check of the page count before sending.
- The portal route list missed `login.store` and suggested a `customers.show` route that does not exist.
- The home page and the README no longer say a fresh installation works "after `composer require` and `php artisan migrate`": it also needs a user for the portal and a mailer.
- README: a `## Requirements` section, the standard section order, links to the documentation site, a section on who can open the portal, and no personal author section.

### Added

- Documentation pages [Quick start](https://arviddejong.github.io/laravel-document-sign/quick-start.html), [Testing](https://arviddejong.github.io/laravel-document-sign/testing.html) and [Troubleshooting](https://arviddejong.github.io/laravel-document-sign/troubleshooting.html), and a "Check that it works" section on the installation page.
- `tests/DocsSiteTest.php` checks that the pages a beginner needs exist and are linked from the home page, that anchors in links point at an existing heading and that the FAQ stays at six to ten questions.

### Removed

- `docs/README.md`, which duplicated the home page of the documentation site.

## [1.1.0] - 2026-09-21

### Security

- **An expired signing link kept working for the PDF and for signing.** Only the signing page checked `link_expires_after_hours`; the address that streams the PDF and the one that receives the signature were reachable with the signer's uuid alone, so someone holding an old mail could still read the document and sign it. Both now answer 403 once `link_expires_after_hours` hours have passed since the invitation was last sent, exactly like the expired link itself (`Illuminate\Routing\Exceptions\InvalidSignatureException`). "Last sent" is the newest `invitation_sent` event of that signer in the audit trail, or the moment the signer was created when there is none, so no migration is needed. **What you have to do:** nothing for the normal flow. When you send an invitation again from your own code, record it, or the new link opens the page but the PDF and the submit stay closed:

  ```php
  $signer->notify(new \Darvis\Signer\Notifications\SignatureRequested($signer));
  app(\Darvis\Signer\Services\AuditLogger::class)->log($signer->document, 'invitation_sent', $signer);
  ```

  Signers need more time? Raise `SIGNER_LINK_EXPIRES_AFTER_HOURS` in `.env`; it counts for open invitations too.
- **A signature was accepted for a cancelled or completed document.** Submitting a signature now answers 403 for a document with the status `Cancelled` or `Completed`. Nothing to do; setting `DocumentStatus::Cancelled` is now enough to stop further signatures.
- **The submitted signature was not checked.** Any base64 text was stored as the signature, broken base64 gave the signer a server error, a file that is not a PNG only failed when the last signer signed, and there was no size limit. The signature must now decode strictly to a real PNG that can be placed on the PDF (not 16 bit, not interlaced), of at most 5000 × 5000 pixels and at most `signature_max_kilobytes` (512). Anything else is a normal validation error on the signing page. Nothing to do; the signature pad of the package always sent such a PNG. With a signing page of your own that sends larger images, set `SIGNER_SIGNATURE_MAX_KILOBYTES`.
- **A failed stamp left the document stuck for ever.** The signer was marked as signed and `SignerSigned` was dispatched before the PDF was stamped. When stamping threw, the signer got a server error, could never submit again and the document stayed `Pending`. Signing now runs in one database transaction with row locks; when anything fails it is rolled back, the written files are removed, `Darvis\Signer\Exceptions\SigningFailed` is reported to your exception handler and the signer is asked to try again. A double submit signs once. **What you have to do:** nothing for new signatures. A document that got stuck before this release (every signer `Signed`, document `Pending`) still has to be finished by hand; the Boost skill and the documentation show how.
- **A long user agent could break signing.** A user agent above 255 characters failed the audit trail insert on MySQL in strict mode, after the signer was already marked as signed. It is now cut to the column length. Nothing to do.
- **A document title with a `/` or `\` broke the PDF.** The title was used as the download file name as it was, which made the response throw, so the signer saw no PDF and the portal download failed. File names now replace path separators, drop control characters and fall back to `document.pdf`. Nothing to do; in your own download responses use `Darvis\Signer\Support\DownloadName::forTitle($document->title)`.
- **The portal login had no limit on attempts.** It is now limited to five attempts a minute for an email address from one IP address; the sixth answers 429. **What you have to do:** nothing. For another limit register your own limiter under the same name in a service provider that boots after the package:

  ```php
  RateLimiter::for('signer-portal-login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
  ```

### Added

- Config key `signature_max_kilobytes` (`SIGNER_SIGNATURE_MAX_KILOBYTES`, default `512`) with `SignerConfig::signatureMaxKilobytes()`. Add it to a published `config/signer.php`; without it the default applies.
- `Signer::lastInvitedAt()`, `Signer::invitationExpired(int $hours)` and the `Signer::auditEvents()` relation.
- `Darvis\Signer\Support\DownloadName`, `Darvis\Signer\Support\SignatureDataUrl`, `Darvis\Signer\Rules\ValidSignature` and `Darvis\Signer\Exceptions\SigningFailed`.
- Dutch translations for the three new messages on the signing page.

### Changed

- `SignerSigned` and `DocumentCompleted` are dispatched after the signature has been committed, and no longer for a signature whose stamping failed. `SignerSigned` used to fire before the stamping of the last signature.
- `SignatureProcessor::sign()` throws `Darvis\Signer\Exceptions\SigningFailed` (a `RuntimeException`, the cause is its previous exception) when storing or stamping fails, instead of letting the cause through, and refuses a signature that is not a usable PNG with an `InvalidArgumentException` before anything is stored.
- `signer.pdf` and `signer.store` answer 403 after `link_expires_after_hours`; `signer.store` answers 403 for a cancelled or completed document; the portal login answers 429 after five attempts a minute.

## [1.0.1] - 2026-09-21

### Added

- Laravel Boost skill `laravel-document-sign-development` in `resources/boost/skills/`: how the signing flow runs, what every failure gives you, checking a PDF before sending, resending an invitation, recovering a document whose stamping failed, the pitfalls in a host app, the settings and how to test the flow without sending mail.
- Social preview image for the documentation site (`docs/assets/images/social-preview.png`), set as the default Open Graph and Twitter card image.

## [1.0.0] - 2026-09-18

First public release of the package: a DocuSign like signing flow for PDF documents in Laravel, with mailed invitations, a signing page with a signature canvas, stamping through FPDI, an audit trail and an optional portal.

### Added

- `Signer::document($path)->title()->forCustomer()->addSigner()->send()` to store a PDF, create the signers and mail each one a temporary signed link.
- Signing page and confirmation page under the `sign` prefix, stamping of every signature on the PDF once all signers are done, and the `SignerSigned` and `DocumentCompleted` events.
- Audit trail in `signer_audit_events` with IP address and user agent.
- Optional portal with login, dashboard, customers with contacts and document management, on by default and switched off with `SIGNER_PORTAL_ENABLED=false`.
- Dutch translation of the portal and the signing page.
- `Darvis\Signer\Support\SignerConfig` as the single reader of the package config.
- Laravel Boost guideline in `resources/boost/guidelines/core.blade.php`, Pint, Larastan level 8 and the shared CI workflow on PHP 8.2 to 8.4 with Laravel 11, 12 and 13.
- Documentation site at https://arviddejong.github.io/laravel-document-sign/, built from `docs/`; the README holds the quick start and links there.

### Changed

- Requires PHP 8.2 and Laravel 11, 12 or 13 (was PHP 8.3 and Laravel 12), and declares the GD extension it always needed.
- The keys in `config/signer.php` are in alphabetical order; values are unchanged.
