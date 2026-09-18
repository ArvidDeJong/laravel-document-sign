<?php

use Darvis\Signer\Enums\DocumentStatus;
use Darvis\Signer\Facades\Signer as SignerFacade;
use Darvis\Signer\Notifications\SignatureRequested;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('signer.disk'));
    Notification::fake();
});

it('creates a pending document and mails every signer', function () {
    $document = SignerFacade::document($this->createSamplePdf())
        ->title('Freelance contract')
        ->addSigner('Alice', 'alice@example.com', page: 1, x: 10, y: 80)
        ->addSigner('Bob', 'bob@example.com', page: 2, x: 55, y: 80)
        ->send();

    expect($document->status)->toBe(DocumentStatus::Pending)
        ->and($document->signers)->toHaveCount(2)
        ->and($document->uuid)->not->toBeNull();

    Storage::disk(config('signer.disk'))->assertExists($document->original_path);
    Notification::assertCount(2);
    Notification::assertSentTo($document->signers->first(), SignatureRequested::class);
});

it('records audit events when the invitations go out', function () {
    $document = SignerFacade::document($this->createSamplePdf())
        ->addSigner('Alice', 'alice@example.com')
        ->send();

    expect($document->auditEvents()->pluck('event')->all())
        ->toContain('document_created', 'invitation_sent');
});

it('refuses to send without signers', function () {
    SignerFacade::document($this->createSamplePdf())->send();
})->throws(InvalidArgumentException::class, 'at least one signer');

it('refuses a pdf path that does not exist', function () {
    SignerFacade::document('/tmp/does-not-exist.pdf');
})->throws(InvalidArgumentException::class, 'not found');
