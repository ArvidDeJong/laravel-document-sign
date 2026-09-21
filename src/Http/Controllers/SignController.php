<?php

namespace Darvis\Signer\Http\Controllers;

use Darvis\Signer\Enums\DocumentStatus;
use Darvis\Signer\Exceptions\SigningFailed;
use Darvis\Signer\Models\Signer;
use Darvis\Signer\Rules\ValidSignature;
use Darvis\Signer\Services\SignatureProcessor;
use Darvis\Signer\Support\DownloadName;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SignController extends Controller
{
    /**
     * Show the signing page for an invited signer.
     */
    public function show(Signer $signer): View|RedirectResponse
    {
        if ($signer->hasSigned()) {
            return redirect()->route('signer.done', $signer);
        }

        return view('signer::sign', ['signer' => $signer->load('document')]);
    }

    /**
     * Stream the original PDF so the signing page can display it.
     */
    public function pdf(Signer $signer, SignerConfig $config): StreamedResponse
    {
        $this->ensureInvitationIsValid($signer, $config);

        return Storage::disk($config->disk())->response(
            $signer->document->original_path,
            DownloadName::forTitle($signer->document->title),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Store the captured signature.
     */
    public function store(
        Request $request,
        Signer $signer,
        SignatureProcessor $processor,
        SignerConfig $config,
    ): RedirectResponse {
        $this->ensureInvitationIsValid($signer, $config);

        abort_if($signer->hasSigned(), 403, 'This document has already been signed.');
        abort_if(
            in_array($signer->document->status, [DocumentStatus::Cancelled, DocumentStatus::Completed], true),
            403,
            __('This document can no longer be signed.'),
        );

        $validated = $request->validate([
            'signature' => ['bail', 'required', 'string', new ValidSignature($config->signatureMaxKilobytes())],
        ]);

        try {
            $processor->sign($signer, $validated['signature'], $request);
        } catch (SigningFailed $e) {
            // Everything was rolled back, so the signer can simply submit again.
            report($e);

            return back()->withErrors([
                'signature' => __('Your signature could not be processed. Please try again.'),
            ]);
        }

        return redirect()->route('signer.done', $signer);
    }

    /**
     * The expiry of the mailed link also counts for the routes that are keyed
     * on the signer uuid alone. It answers like the `signed` middleware does
     * on `signer.show`, so a host app renders one page for both.
     */
    protected function ensureInvitationIsValid(Signer $signer, SignerConfig $config): void
    {
        if ($signer->invitationExpired($config->linkExpiresAfterHours())) {
            throw new InvalidSignatureException;
        }
    }

    /**
     * Show the confirmation page after signing.
     */
    public function done(Signer $signer): View
    {
        abort_unless($signer->hasSigned(), 404);

        return view('signer::done', ['signer' => $signer->load('document')]);
    }
}
