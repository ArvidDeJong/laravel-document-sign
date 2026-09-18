<?php

use Darvis\Signer\Models\Customer;
use Darvis\Signer\Models\Document;
use Darvis\Signer\Notifications\SignatureRequested;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('signer.disk'));
    Notification::fake();
    $this->actingAs($this->createAdminUser());
});

function fakePdfUpload($test): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'contract.pdf',
        file_get_contents($test->createSamplePdf()),
    );
}

it('creates and sends a document from the portal', function () {
    $customer = Customer::create(['name' => 'Acme BV']);

    $this->post(route('signer.portal.documents.store'), [
        'title' => 'Freelance contract',
        'pdf' => fakePdfUpload($this),
        'customer_id' => $customer->id,
        'signers' => [
            ['name' => 'Alice', 'email' => 'alice@example.com', 'page' => 1, 'x' => 10, 'y' => 80],
        ],
    ])->assertRedirect();

    $document = Document::first();

    expect($document->customer_id)->toBe($customer->id)
        ->and($document->signers)->toHaveCount(1);

    Storage::disk(config('signer.disk'))->assertExists($document->original_path);
    Notification::assertCount(1);
    Notification::assertSentTo($document->signers->first(), SignatureRequested::class);
});

it('requires at least one signer', function () {
    $this->post(route('signer.portal.documents.store'), [
        'title' => 'Contract',
        'pdf' => fakePdfUpload($this),
        'signers' => [],
    ])->assertSessionHasErrors('signers');
});

it('rejects a non pdf upload', function () {
    $this->post(route('signer.portal.documents.store'), [
        'title' => 'Contract',
        // A fake upload reports the mime type of its name, so the name must be non pdf too.
        'pdf' => UploadedFile::fake()->create('contract.txt', 1, 'text/plain'),
        'signers' => [
            ['name' => 'Alice', 'email' => 'alice@example.com', 'page' => 1, 'x' => 10, 'y' => 80],
        ],
    ])->assertSessionHasErrors('pdf');
});

it('shows a document with its audit trail', function () {
    $document = createPortalDocument($this);

    $this->get(route('signer.portal.documents.show', $document))
        ->assertOk()
        ->assertSee($document->title);
});

it('downloads the original pdf', function () {
    $document = createPortalDocument($this);

    $this->get(route('signer.portal.documents.download', [$document, 'original']))
        ->assertOk()
        ->assertDownload($document->title.'.pdf');
});

it('returns 404 for a signed download when nobody signed yet', function () {
    $document = createPortalDocument($this);

    $this->get(route('signer.portal.documents.download', [$document, 'signed']))
        ->assertNotFound();
});

it('deletes a document including its stored files', function () {
    $document = createPortalDocument($this);
    $path = $document->original_path;

    $this->delete(route('signer.portal.documents.destroy', $document))
        ->assertRedirect(route('signer.portal.documents.index'));

    $this->assertDatabaseCount('signer_documents', 0);
    Storage::disk(config('signer.disk'))->assertMissing($path);
});

function createPortalDocument($test): Document
{
    $test->post(route('signer.portal.documents.store'), [
        'title' => 'Contract',
        'pdf' => fakePdfUpload($test),
        'signers' => [
            ['name' => 'Alice', 'email' => 'alice@example.com', 'page' => 1, 'x' => 10, 'y' => 80],
        ],
    ]);

    return Document::with('signers')->first();
}
