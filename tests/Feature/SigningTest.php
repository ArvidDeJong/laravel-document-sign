<?php

use Darvis\Signer\Enums\DocumentStatus;
use Darvis\Signer\Enums\SignerStatus;
use Darvis\Signer\Events\DocumentCompleted;
use Darvis\Signer\Events\SignerSigned;
use Darvis\Signer\Facades\Signer as SignerFacade;
use Darvis\Signer\Models\Document;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake(config('signer.disk'));
    Notification::fake();
});

function createPendingDocument($test, int $signerCount = 1): Document
{
    $builder = SignerFacade::document($test->createSamplePdf())->title('Contract');

    foreach (range(1, $signerCount) as $index) {
        $builder->addSigner("Signer {$index}", "signer{$index}@example.com", page: 1, x: 10, y: 70 + $index * 5);
    }

    return $builder->send();
}

function signingUrl($signer): string
{
    return URL::temporarySignedRoute('signer.show', now()->addHour(), ['signer' => $signer->uuid]);
}

it('shows the signing page through a valid signed link', function () {
    $signer = createPendingDocument($this)->signers->first();

    $this->get(signingUrl($signer))
        ->assertOk()
        ->assertSee('Contract')
        ->assertSee('signature-pad', false);
});

it('rejects a signing link without a valid signature parameter', function () {
    $signer = createPendingDocument($this)->signers->first();

    $this->get(route('signer.show', $signer))->assertForbidden();
});

it('streams the original pdf for the signing page', function () {
    $signer = createPendingDocument($this)->signers->first();

    $this->get(route('signer.pdf', $signer))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('lets a signer sign and completes a single signer document', function () {
    Event::fake([SignerSigned::class, DocumentCompleted::class]);

    $document = createPendingDocument($this);
    $signer = $document->signers->first();

    $this->post(route('signer.store', $signer), ['signature' => $this->signatureDataUrl()])
        ->assertRedirect(route('signer.done', $signer));

    $signer->refresh();
    $document->refresh();

    expect($signer->status)->toBe(SignerStatus::Signed)
        ->and($signer->signed_at)->not->toBeNull()
        ->and($document->status)->toBe(DocumentStatus::Completed)
        ->and($document->signed_path)->not->toBeNull();

    Storage::disk(config('signer.disk'))->assertExists($signer->signature_path);
    Storage::disk(config('signer.disk'))->assertExists($document->signed_path);

    Event::assertDispatched(SignerSigned::class);
    Event::assertDispatched(DocumentCompleted::class);
});

it('keeps the document pending until every signer has signed', function () {
    $document = createPendingDocument($this, signerCount: 2);
    [$first, $second] = $document->signers;

    $this->post(route('signer.store', $first), ['signature' => $this->signatureDataUrl()]);

    expect($document->refresh()->status)->toBe(DocumentStatus::Pending);

    $this->post(route('signer.store', $second), ['signature' => $this->signatureDataUrl()]);

    expect($document->refresh()->status)->toBe(DocumentStatus::Completed);
});

it('produces a signed pdf that still is a valid pdf', function () {
    $document = createPendingDocument($this);

    $this->post(route('signer.store', $document->signers->first()), [
        'signature' => $this->signatureDataUrl(),
    ]);

    $contents = Storage::disk(config('signer.disk'))->get($document->refresh()->signed_path);

    expect($contents)->toStartWith('%PDF');
});

it('records ip address and user agent in the audit trail', function () {
    $document = createPendingDocument($this);

    $this->withHeaders(['User-Agent' => 'PestBrowser/1.0'])
        ->post(route('signer.store', $document->signers->first()), [
            'signature' => $this->signatureDataUrl(),
        ]);

    $event = $document->auditEvents()->where('event', 'document_signed')->first();

    expect($event)->not->toBeNull()
        ->and($event->ip_address)->not->toBeNull()
        ->and($event->user_agent)->toBe('PestBrowser/1.0');
});

it('blocks signing twice', function () {
    $signer = createPendingDocument($this)->signers->first();

    $this->post(route('signer.store', $signer), ['signature' => $this->signatureDataUrl()]);

    $this->post(route('signer.store', $signer), ['signature' => $this->signatureDataUrl()])
        ->assertForbidden();
});

it('rejects a signature that is not a png data url', function () {
    $signer = createPendingDocument($this)->signers->first();

    $this->post(route('signer.store', $signer), ['signature' => 'not-an-image'])
        ->assertSessionHasErrors('signature');

    expect($signer->refresh()->status)->toBe(SignerStatus::Pending);
});

it('redirects an already signed signer from the signing page to the done page', function () {
    $signer = createPendingDocument($this)->signers->first();

    $this->post(route('signer.store', $signer), ['signature' => $this->signatureDataUrl()]);

    $this->get(signingUrl($signer))
        ->assertRedirect(route('signer.done', $signer));
});
