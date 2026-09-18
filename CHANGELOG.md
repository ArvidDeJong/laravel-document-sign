# Changelog

All notable changes to `darvis/laravel-document-sign` are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
