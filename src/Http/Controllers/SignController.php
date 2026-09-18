<?php

namespace Darvis\Signer\Http\Controllers;

use Darvis\Signer\Models\Signer;
use Darvis\Signer\Services\SignatureProcessor;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        return Storage::disk($config->disk())->response(
            $signer->document->original_path,
            $signer->document->title.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Store the captured signature.
     */
    public function store(Request $request, Signer $signer, SignatureProcessor $processor): RedirectResponse
    {
        abort_if($signer->hasSigned(), 403, 'This document has already been signed.');

        $validated = $request->validate([
            'signature' => ['required', 'string', 'regex:/^data:image\/png;base64,/'],
        ]);

        $processor->sign($signer, $validated['signature'], $request);

        return redirect()->route('signer.done', $signer);
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
