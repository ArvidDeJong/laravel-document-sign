<?php

use Darvis\Signer\Enums\DocumentStatus;
use Darvis\Signer\Enums\SignerStatus;
use Darvis\Signer\Events\DocumentCompleted;
use Darvis\Signer\Events\SignerSigned;
use Darvis\Signer\Facades\Signer as SignerFacade;
use Darvis\Signer\Models\Document;
use Darvis\Signer\Notifications\SignatureRequested;
use Darvis\Signer\Services\AuditLogger;
use Darvis\Signer\Services\SignatureProcessor;
use Darvis\Signer\Services\SignatureStamper;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('signer.disk'));
    Notification::fake();
});

function hardeningDocument($test, string $title = 'Contract', int $signerCount = 1): Document
{
    $builder = SignerFacade::document($test->createSamplePdf())->title($title);

    foreach (range(1, $signerCount) as $index) {
        $builder->addSigner("Signer {$index}", "signer{$index}@example.com", page: 1, x: 10, y: 70 + $index * 5);
    }

    return $builder->send();
}

function pngDataUrl(int $width, int $height, bool $noise = false): string
{
    $image = imagecreatetruecolor($width, $height);

    if ($noise) {
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                imagesetpixel($image, $x, $y, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            }
        }
    }

    ob_start();
    imagepng($image);

    return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
}

/**
 * A stamper that fails until it is told to work again.
 */
function failingStamper(): SignatureStamper
{
    return new class(app(SignerConfig::class)) extends SignatureStamper
    {
        public bool $fail = true;

        public function stamp(Document $document): string
        {
            if ($this->fail) {
                throw new RuntimeException('The stamper broke.');
            }

            return parent::stamp($document);
        }
    };
}

// Link expiry

it('stops streaming the pdf once the signing link has expired', function () {
    $signer = hardeningDocument($this)->signers->first();

    $this->travel(71)->hours();
    $this->get(route('signer.pdf', $signer))->assertOk();

    $this->travel(2)->hours();
    $this->get(route('signer.pdf', $signer))->assertForbidden();
});

it('refuses a signature once the signing link has expired', function () {
    $signer = hardeningDocument($this)->signers->first();

    $this->travel(73)->hours();

    $this->post(route('signer.store', $signer), ['signature' => $this->signatureDataUrl()])
        ->assertForbidden();

    expect($signer->refresh()->status)->toBe(SignerStatus::Pending);
});

it('follows the configured number of hours', function () {
    config()->set('signer.link_expires_after_hours', 1);

    $signer = hardeningDocument($this)->signers->first();

    $this->travel(2)->hours();

    $this->get(route('signer.pdf', $signer))->assertForbidden();
});

it('reopens the window when the invitation is sent again', function () {
    $signer = hardeningDocument($this)->signers->first();

    $this->travel(100)->hours();
    $this->get(route('signer.pdf', $signer))->assertForbidden();

    $signer->notify(new SignatureRequested($signer));
    app(AuditLogger::class)->log($signer->document, 'invitation_sent', $signer);

    $this->get(route('signer.pdf', $signer))->assertOk();

    $this->post(route('signer.store', $signer), ['signature' => $this->signatureDataUrl()])
        ->assertRedirect(route('signer.done', $signer));
});

it('does not reopen the window for another signer of the same document', function () {
    [$first, $second] = hardeningDocument($this, signerCount: 2)->signers;

    $this->travel(100)->hours();

    app(AuditLogger::class)->log($first->document, 'invitation_sent', $first);

    $this->get(route('signer.pdf', $first))->assertOk();
    $this->get(route('signer.pdf', $second))->assertForbidden();
});

it('falls back to the creation date of a signer without an invitation in the audit trail', function () {
    $signer = hardeningDocument($this)->signers->first();
    $signer->document->auditEvents()->where('event', 'invitation_sent')->delete();

    $this->get(route('signer.pdf', $signer))->assertOk();

    $this->travel(73)->hours();

    $this->get(route('signer.pdf', $signer))->assertForbidden();
});

it('refuses a signature for a document that is cancelled or completed', function (DocumentStatus $status) {
    $document = hardeningDocument($this);
    $document->update(['status' => $status]);
    $signer = $document->signers->first();

    $this->post(route('signer.store', $signer), ['signature' => $this->signatureDataUrl()])
        ->assertForbidden();

    expect($signer->refresh()->status)->toBe(SignerStatus::Pending);
})->with([
    'cancelled' => DocumentStatus::Cancelled,
    'completed' => DocumentStatus::Completed,
]);

// Signature validation

it('rejects a bad signature as a validation error', function (mixed $signature) {
    $signer = hardeningDocument($this)->signers->first();

    $this->post(route('signer.store', $signer), ['signature' => $signature])
        ->assertStatus(302)
        ->assertSessionHasErrors('signature');

    expect($signer->refresh()->status)->toBe(SignerStatus::Pending);
    expect(Storage::disk(config('signer.disk'))->allFiles(config('signer.storage_path').'/signatures'))->toBe([]);
})->with([
    'invalid base64' => 'data:image/png;base64,@@@not base64@@@',
    'an empty image' => 'data:image/png;base64,',
    'text instead of an image' => 'data:image/png;base64,'.base64_encode('<?php echo 1;'),
    'a gif with a png prefix' => fn () => 'data:image/png;base64,'.base64_encode((function () {
        ob_start();
        imagegif(imagecreatetruecolor(10, 10));

        return (string) ob_get_clean();
    })()),
    'only the png magic bytes' => 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\n".str_repeat('x', 40)),
    'an array' => [['data:image/png;base64,AAAA']],
    'an image with absurd dimensions' => fn () => pngDataUrl(6000, 10),
    'an interlaced png, which the stamper cannot read' => fn () => 'data:image/png;base64,'.base64_encode((function () {
        $image = imagecreatetruecolor(20, 20);
        imageinterlace($image, true);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    })()),
]);

it('rejects a signature above the configured size', function () {
    config()->set('signer.signature_max_kilobytes', 2);

    $signer = hardeningDocument($this)->signers->first();

    $this->post(route('signer.store', $signer), ['signature' => pngDataUrl(100, 100, noise: true)])
        ->assertSessionHasErrors('signature');

    expect($signer->refresh()->status)->toBe(SignerStatus::Pending);
});

it('accepts signatures up to 512 kilobytes by default', function () {
    expect(app(SignerConfig::class)->signatureMaxKilobytes())->toBe(512);
});

// Atomic signing

it('leaves the signer able to retry when stamping fails', function () {
    Event::fake([SignerSigned::class, DocumentCompleted::class]);

    $stamper = failingStamper();
    $this->app->instance(SignatureStamper::class, $stamper);

    $document = hardeningDocument($this);
    $signer = $document->signers->first();

    $this->from(signingUrl($signer))
        ->post(route('signer.store', $signer), ['signature' => $this->signatureDataUrl()])
        ->assertRedirect(signingUrl($signer))
        ->assertSessionHasErrors('signature');

    expect($signer->refresh()->status)->toBe(SignerStatus::Pending)
        ->and($signer->signature_path)->toBeNull()
        ->and($signer->signed_at)->toBeNull()
        ->and($document->refresh()->status)->toBe(DocumentStatus::Pending)
        ->and($document->auditEvents()->where('event', 'document_signed')->count())->toBe(0);

    expect(Storage::disk(config('signer.disk'))->allFiles(config('signer.storage_path').'/signatures'))->toBe([]);

    Event::assertNotDispatched(SignerSigned::class);
    Event::assertNotDispatched(DocumentCompleted::class);

    $stamper->fail = false;

    $this->post(route('signer.store', $signer), ['signature' => $this->signatureDataUrl()])
        ->assertRedirect(route('signer.done', $signer));

    expect($document->refresh()->status)->toBe(DocumentStatus::Completed);

    Event::assertDispatchedTimes(SignerSigned::class, 1);
    Event::assertDispatchedTimes(DocumentCompleted::class, 1);
});

it('reports the exception that made stamping fail', function () {
    $this->app->instance(SignatureStamper::class, failingStamper());

    $reported = [];
    $this->app->make(ExceptionHandler::class)
        ->reportable(function (Throwable $e) use (&$reported) {
            $reported[] = $e;

            return false;
        });

    $signer = hardeningDocument($this)->signers->first();

    $this->post(route('signer.store', $signer), ['signature' => $this->signatureDataUrl()]);

    expect($reported)->toHaveCount(1)
        ->and($reported[0]->getPrevious()?->getMessage() ?? $reported[0]->getMessage())->toBe('The stamper broke.');
});

it('keeps the earlier signers signed when stamping fails for the last one', function () {
    $stamper = failingStamper();
    $this->app->instance(SignatureStamper::class, $stamper);

    [$first, $second] = hardeningDocument($this, signerCount: 2)->signers;

    $this->post(route('signer.store', $first), ['signature' => $this->signatureDataUrl()])
        ->assertRedirect(route('signer.done', $first));

    $this->post(route('signer.store', $second), ['signature' => $this->signatureDataUrl()])
        ->assertSessionHasErrors('signature');

    expect($first->refresh()->status)->toBe(SignerStatus::Signed)
        ->and($second->refresh()->status)->toBe(SignerStatus::Pending);

    Storage::disk(config('signer.disk'))->assertExists($first->signature_path);
});

it('cuts a long user agent down to the column length', function () {
    $document = hardeningDocument($this);

    $this->withHeaders(['User-Agent' => str_repeat('a', 400)])
        ->post(route('signer.store', $document->signers->first()), ['signature' => $this->signatureDataUrl()])
        ->assertRedirect();

    $event = $document->auditEvents()->where('event', 'document_signed')->firstOrFail();

    expect(mb_strlen((string) $event->user_agent))->toBe(255);
});

it('signs once when the same signature is submitted twice', function () {
    Event::fake([SignerSigned::class, DocumentCompleted::class]);

    $document = hardeningDocument($this);
    $signer = $document->signers->first();
    $stale = $signer->fresh();

    $processor = app(SignatureProcessor::class);
    $processor->sign($signer, $this->signatureDataUrl());
    $result = $processor->sign($stale, $this->signatureDataUrl());

    expect($result->hasSigned())->toBeTrue()
        ->and($document->auditEvents()->where('event', 'document_signed')->count())->toBe(1)
        ->and($document->auditEvents()->where('event', 'document_completed')->count())->toBe(1);

    Event::assertDispatchedTimes(SignerSigned::class, 1);
    Event::assertDispatchedTimes(DocumentCompleted::class, 1);
});

it('refuses a payload that is not a png when the processor is called directly', function () {
    $signer = hardeningDocument($this)->signers->first();

    expect(fn () => app(SignatureProcessor::class)->sign($signer, 'data:image/png;base64,'.base64_encode('nope')))
        ->toThrow(InvalidArgumentException::class);

    expect($signer->refresh()->status)->toBe(SignerStatus::Pending);
});

it('translates the new signing messages to dutch', function () {
    app()->setLocale('nl');

    expect(__('The signature could not be read. Please draw it again.'))->toStartWith('De handtekening')
        ->and(__('Your signature could not be processed. Please try again.'))->toStartWith('Je handtekening')
        ->and(__('This document can no longer be signed.'))->toStartWith('Dit document');
});

// Download file name

it('streams the pdf for a title with path separators', function (string $title) {
    $signer = hardeningDocument($this, $title)->signers->first();

    $this->get(route('signer.pdf', $signer))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
})->with([
    'a slash' => 'Contract 2026/09',
    'a backslash' => 'Contract\\2026',
    'only separators' => '//',
    'characters outside ascii' => "Overeenkomst caf\u{E9} 100% \u{1F58A}\u{FE0F}/2026",
]);

it('downloads a document with path separators in the title from the portal', function () {
    $document = hardeningDocument($this, 'Contract 2026/09');

    $this->actingAs($this->createAdminUser())
        ->get(route('signer.portal.documents.download', [$document, 'original']))
        ->assertOk()
        ->assertDownload('Contract 2026-09.pdf');
});
