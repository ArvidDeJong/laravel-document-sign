---
title: "Portal"
description: "The optional portal: what it offers, who can log in, that every user sees every document, how to restrict it with middleware, and how to turn it off."
nav_order: 6
---

# Portal

After installation the portal is available at `/portal`. It offers:

- a dashboard with counters and the recent documents;
- customers, companies or persons, each with contact persons;
- sending documents: upload a PDF, pick a customer, prefill the signers from the customer's contacts and place each signature;
- a document page with the status per signer, the audit trail and downloads of the original and the signed PDF.

The UI uses Tailwind from a CDN, so there is no build step. Texts are English with a Dutch translation; set the application locale to `nl` for a Dutch portal.

## Who can log in and what they see

The portal is on by default and logs in against the guard in `signer.portal.guard` (`web` by default). A guard is Laravel's name for a way of logging in; `web` is the normal session login on your `users` table.

Create the first user in your application's `users` table, see [Installation & configuration](installation.md#step-4-create-the-first-portal-user).

**Every user who can log in on that guard sees every customer and every document, can download every PDF and can delete them.** The package has no roles and no permissions. When your application has users who must not see the documents, for example customers with an account, do one of these before you go live:

- Turn the portal off with `SIGNER_PORTAL_ENABLED=false`, see [Without the portal](#without-the-portal).
- Add middleware of your own to `signer.portal.middleware`. It runs on every portal route, the login page included, so let guests through and stop logged in users who are not allowed.

`app/Http/Middleware/EnsurePortalAccess.php`, with `is_admin` as an example of a column in your own `users` table:

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user !== null && ! $user->is_admin, 403);

        return $next($request);
    }
}
```

`config/signer.php`, after publishing it with `php artisan vendor:publish --tag=signer-config`:

```php
'portal' => [
    'enabled' => env('SIGNER_PORTAL_ENABLED', true),
    'guard' => 'web',
    'middleware' => ['web', \App\Http\Middleware\EnsurePortalAccess::class],
    'prefix' => 'portal',
],
```

A user who is not an admin can still reach the login form, but gets a 403 on every portal page once logged in.

## The login is rate limited

The login allows five attempts a minute for an email address from one IP address; the sixth answers 429. The limiter is named `signer-portal-login`. For another limit, register a limiter of your own under that name in the `boot()` method of `app/Providers/AppServiceProvider.php`; the providers of your application boot after the package, so yours wins:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('signer-portal-login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
```

## Routes

All portal routes are named `signer.portal.*` and live under `signer.portal.prefix`: `login`, `login.store`, `logout`, `dashboard`, `customers.*` (without `show`), `customers.contacts.store`, `contacts.destroy` and `documents.index`, `create`, `store`, `show`, `download` and `destroy`. Guests are redirected to the portal login, not to the application's own login page.

## Without the portal

Set `SIGNER_PORTAL_ENABLED=false` to register only the signing routes. The [signing flow](signing.md) through the facade keeps working, and you build your own screens on the `Document`, `Signer`, `Customer` and `Contact` models.
