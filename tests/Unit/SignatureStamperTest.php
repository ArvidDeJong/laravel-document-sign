<?php

use Darvis\Signer\Facades\Signer as SignerFacade;
use Darvis\Signer\Services\SignatureProcessor;
use Darvis\Signer\Services\SignatureStamper;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('signer.disk'));
    Notification::fake();
});

it('stamps signatures on the correct pages and keeps the page count', function () {
    $document = SignerFacade::document($this->createSamplePdf())
        ->addSigner('Alice', 'alice@example.com', page: 1)
        ->addSigner('Bob', 'bob@example.com', page: 2)
        ->send();

    $processor = app(SignatureProcessor::class);

    foreach ($document->signers as $signer) {
        $processor->sign($signer, $this->signatureDataUrl());
    }

    $signed = Storage::disk(config('signer.disk'))->get($document->refresh()->signed_path);

    expect($signed)->toStartWith('%PDF')
        ->and(substr_count($signed, '/Type /Page'))->toBeGreaterThanOrEqual(2);
});

it('fails when a signed signer misses a signature image', function () {
    $document = SignerFacade::document($this->createSamplePdf())
        ->addSigner('Alice', 'alice@example.com')
        ->send();

    $document->signers->first()->update(['status' => 'signed']);

    app(SignatureStamper::class)->stamp($document->refresh()->load('signers'));
})->throws(RuntimeException::class, 'no captured signature');
