---
title: Portal
description: "The portal of darvis/laravel-document-sign: dashboard, customers with contacts, sending and following documents, who can log in, and how to run the signing flow without it."
nav_order: 5
---

# Portal

After installation the portal is available at `/portal`. It offers:

- a dashboard with counters and the recent documents;
- customers, companies or persons, each with contact persons;
- sending documents: upload a PDF, pick a customer, prefill the signers from the customer's contacts and place each signature;
- a document page with the status per signer, the audit trail and downloads of the original and the signed PDF.

The UI uses Tailwind from a CDN, so there is no build step. Texts are English with a Dutch translation; set the application locale to `nl` for a Dutch portal.

## Who can log in

The portal authenticates against the guard in `signer.portal.guard` (`web` by default), so any user of the host application can log in. There is no role or permission model inside the package: put your own middleware in `signer.portal.middleware` when only some users may reach it.

The login is rate limited to five attempts a minute for an email address from one IP address; the sixth answers 429. The limiter is named `signer-portal-login`. Register a limiter of your own under that name, in a service provider that boots after the package, for another limit:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('signer-portal-login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
```

Create the first user in your application's `users` table; see [Installation & configuration](installation.md#the-first-portal-user).

## Routes

All portal routes are named `signer.portal.*` and live under `signer.portal.prefix`: `login`, `logout`, `dashboard`, `customers.*`, `customers.contacts.store`, `contacts.destroy` and `documents.*`. Guests are redirected to the portal login, not to the application's own login page.

## Without the portal

Set `SIGNER_PORTAL_ENABLED=false` to register only the signing routes. The [signing flow](signing.md) through the facade keeps working, and you build your own screens on the `Document`, `Signer`, `Customer` and `Contact` models.
