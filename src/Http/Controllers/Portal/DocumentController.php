<?php

namespace Darvis\Signer\Http\Controllers\Portal;

use Darvis\Signer\Models\Customer;
use Darvis\Signer\Models\Document;
use Darvis\Signer\Services\SignerManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(): View
    {
        return view('signer::portal.documents.index', [
            'documents' => Document::with(['customer', 'signers'])->latest()->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('signer::portal.documents.create', [
            'customers' => Customer::with('contacts')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, SignerManager $manager): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'pdf' => ['required', 'file', 'mimetypes:application/pdf'],
            'customer_id' => ['nullable', 'exists:signer_customers,id'],
            'signers' => ['required', 'array', 'min:1'],
            'signers.*.name' => ['required', 'string', 'max:255'],
            'signers.*.email' => ['required', 'email', 'max:255'],
            'signers.*.page' => ['required', 'integer', 'min:1'],
            'signers.*.x' => ['required', 'numeric', 'between:0,100'],
            'signers.*.y' => ['required', 'numeric', 'between:0,100'],
        ]);

        $builder = $manager->document($request->file('pdf')->getRealPath())
            ->title($validated['title'])
            ->forCustomer(Customer::find($validated['customer_id'] ?? null));

        foreach ($validated['signers'] as $signer) {
            $builder->addSigner(
                $signer['name'],
                $signer['email'],
                page: (int) $signer['page'],
                x: (float) $signer['x'],
                y: (float) $signer['y'],
            );
        }

        $document = $builder->send();

        return redirect()
            ->route('signer.portal.documents.show', $document)
            ->with('status', __('Document sent to all signers.'));
    }

    public function show(Document $document): View
    {
        return view('signer::portal.documents.show', [
            'document' => $document->load(['customer', 'signers', 'auditEvents.signer']),
        ]);
    }

    public function download(Document $document, string $type): StreamedResponse
    {
        $path = $type === 'signed' ? $document->signed_path : $document->original_path;

        abort_if($path === null, 404);

        return Storage::disk(config('signer.disk'))->download($path, $document->title.'.pdf');
    }

    public function destroy(Document $document): RedirectResponse
    {
        $disk = Storage::disk(config('signer.disk'));

        $paths = array_filter([
            $document->original_path,
            $document->signed_path,
            ...$document->signers->pluck('signature_path')->all(),
        ]);

        $disk->delete($paths);
        $document->delete();

        return redirect()
            ->route('signer.portal.documents.index')
            ->with('status', __('Document deleted.'));
    }
}
