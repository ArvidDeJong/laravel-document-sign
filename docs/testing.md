---
title: "Testing"
description: "Test the signing flow in your own Laravel app without sending mail: fake the disk and the notification, build a small PDF, post a signature and assert."
nav_order: 7
---

# Testing

You can test the whole signing flow in your application without sending mail and without writing to the real disk. The test builds its own small PDF with FPDF, which the package installs, so you need no fixture file.

## What to fake

| Fake | Why |
| --- | --- |
| `Storage::fake($disk)` | Originals, signatures and signed PDFs go to a temporary disk that is emptied between tests |
| `Notification::fake()` | The invitation is a notification (`Darvis\Signer\Notifications\SignatureRequested`), so no mail goes out and you can assert that it was sent |
| `Event::fake([...])` with the event classes | Only when you want to assert the events; see the warning below |

`Mail::fake()` also stops the invitation, but `Mail::assertSent()` does not see it, because it is a notification and not a mailable. Use `Notification::fake()` when you want to assert.

## A complete test

`tests/Feature/ContractSigningTest.php`, in [Pest](https://pestphp.com), with the `RefreshDatabase` trait active for feature tests:

```php
<?php

use Darvis\Signer\Enums\DocumentStatus;
use Darvis\Signer\Events\DocumentCompleted;
use Darvis\Signer\Events\SignerSigned;
use Darvis\Signer\Facades\Signer;
use Darvis\Signer\Notifications\SignatureRequested;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

it('completes the document when the signer signs', function () {
    $disk = app(SignerConfig::class)->disk();

    Storage::fake($disk);
    Notification::fake();
    Event::fake([SignerSigned::class, DocumentCompleted::class]);

    // A real one page PDF.
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 14);
    $pdf->Cell(0, 10, 'Test contract');
    $path = tempnam(sys_get_temp_dir(), 'contract').'.pdf';
    $pdf->Output('F', $path);

    $document = Signer::document($path)
        ->title('Test contract')
        ->addSigner('Alice Jansen', 'alice@example.com', page: 1, x: 10, y: 80)
        ->send();

    $signer = $document->signers->first();

    Notification::assertSentTo($signer, SignatureRequested::class);
    expect($document->status)->toBe(DocumentStatus::Pending);

    // A real small PNG, like the signature pad sends.
    $image = imagecreatetruecolor(120, 40);
    imageline($image, 10, 30, 110, 10, imagecolorallocate($image, 255, 255, 255));
    ob_start();
    imagepng($image);
    $signature = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());

    $this->post(route('signer.store', $signer), ['signature' => $signature])
        ->assertRedirect(route('signer.done', $signer));

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Completed);
    Storage::disk($disk)->assertExists($document->signed_path);
    Event::assertDispatched(SignerSigned::class);
    Event::assertDispatched(DocumentCompleted::class);
});
```

The test sends a document, checks the invitation, posts a signature the way the signing page does, and asserts that the document is completed and the signed PDF exists. In a PHPUnit class the same calls work inside a test method.

## Things that trip up a test

- **Pass the event classes to `Event::fake([...])`.** A bare `Event::fake()` also silences the model events that fill the `uuid` columns, and `send()` then fails with a database error about `signer_documents.uuid`.
- **The signature must be a real PNG.** Any other value is a validation error on the field `signature` (`assertSessionHasErrors('signature')`) and nothing is stored.
- **Open the signing page with a signed link.** `route('signer.show', $signer)` without a signature answers 403. Build the link the way the mail does:

  ```php
  use Illuminate\Support\Facades\URL;

  $url = URL::temporarySignedRoute('signer.show', now()->addHour(), ['signer' => $signer->uuid]);

  $this->get($url)->assertOk();
  ```

- **Test the expiry with time travel.** After `$this->travel(73)->hours()` the routes `signer.pdf` and `signer.store` answer 403 with the default of 72 hours.
- **The portal login is rate limited.** A test that logs in more than five times with one email address gets a 429; call `$this->travel(61)->seconds()` in between.
- **Log in to the portal with `actingAs()`.** `$this->actingAs($user)->get(route('signer.portal.dashboard'))` answers 200; a guest is redirected to `signer.portal.login`.
- **Set config inside the test, before the code runs**, with `config()->set('signer.link_expires_after_hours', 1)`. The route prefixes, the middleware and `portal.enabled` cannot be changed after the application has booted.

## Testing a failed stamp

To see what your application does when stamping fails, bind a stamper that throws:

```php
use Darvis\Signer\Enums\SignerStatus;
use Darvis\Signer\Models\Document;
use Darvis\Signer\Services\SignatureStamper;
use Darvis\Signer\Support\SignerConfig;

$this->app->instance(SignatureStamper::class, new class(app(SignerConfig::class)) extends SignatureStamper
{
    public function stamp(Document $document): string
    {
        throw new RuntimeException('The stamper broke.');
    }
});

$this->post(route('signer.store', $signer), ['signature' => $signature])
    ->assertSessionHasErrors('signature');

expect($signer->refresh()->status)->toBe(SignerStatus::Pending);
```

The signer stays `Pending`, no event is dispatched and the signer can submit again, see [When signing fails](signing.md#when-signing-fails).
