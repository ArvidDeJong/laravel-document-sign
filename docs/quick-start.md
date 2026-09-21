---
title: "Quick start"
description: "A complete copy and paste example: send a PDF contract for signing from a Laravel controller and receive the signed PDF in an event listener."
nav_order: 3
---

# Quick start

This page sends one PDF to one signer and picks up the signed PDF afterwards. It assumes the package is installed and the migrations have run, see [Installation & configuration](installation.md).

## 1. Put a PDF in place

Save a PDF as `storage/app/contracts/contract.pdf`. The builder reads a file path on the server, not a path on a Laravel filesystem disk. A PDF that lives on a disk such as S3 has to be written to a local temporary file first.

## 2. Send the document

`app/Http/Controllers/ContractController.php`:

```php
<?php

namespace App\Http\Controllers;

use Darvis\Signer\Facades\Signer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $document = Signer::document(storage_path('app/contracts/contract.pdf'))
            ->title('Freelance agreement')
            ->addSigner($validated['name'], $validated['email'], page: 1, x: 10, y: 80)
            ->send();

        return back()->with('status', 'Sent for signing: '.$document->uuid);
    }
}
```

`Signer::document()` throws an `InvalidArgumentException` when the file does not exist. `send()` copies the PDF to the configured disk, creates a `Document` with the status `Pending` and one `Signer`, and mails the signer a link to the signing page. `page: 1, x: 10, y: 80` places the signature on page 1, 10 percent from the left and 80 percent from the top.

`routes/web.php`:

```php
use App\Http\Controllers\ContractController;
use Illuminate\Support\Facades\Route;

Route::post('/contracts/send', [ContractController::class, 'send'])->middleware('auth');
```

The `auth` middleware makes sure only logged in users of your application can send a contract. Post a `name` and an `email` to this route from a form of your own.

## 3. Pick up the signed PDF

`app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Darvis\Signer\Events\DocumentCompleted;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(function (DocumentCompleted $event): void {
            $pdf = Storage::disk(app(SignerConfig::class)->disk())
                ->get($event->document->signed_path);

            // Mail $pdf to the parties, or copy it to your own archive.
        });
    }
}
```

The package dispatches `DocumentCompleted` after the last signer has signed and the signed PDF is stored. The listener runs inside the request of that signer, so put slow work such as sending mail in a [queued listener](https://laravel.com/docs/events#queued-event-listeners).

## What happens when it runs

1. The signer receives the mail "Signature requested: Freelance agreement" with the button "Review and sign".
2. The link opens the signing page. It is valid for 72 hours (`link_expires_after_hours`).
3. The signer draws a signature and confirms. The package stores the signature, stamps it on the PDF and sets the document to `Completed`.
4. Your listener receives the signed PDF.

Next: [Signing flow](signing.md) for several signers, positions and the status, and [Testing](testing.md) to cover this in a test.
