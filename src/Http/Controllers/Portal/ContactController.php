<?php

namespace Darvis\Signer\Http\Controllers\Portal;

use Darvis\Signer\Models\Contact;
use Darvis\Signer\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ContactController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $customer->contacts()->create($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]));

        return redirect()
            ->route('signer.portal.customers.edit', $customer)
            ->with('status', __('Contact added.'));
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $customer = $contact->customer;
        $contact->delete();

        return redirect()
            ->route('signer.portal.customers.edit', $customer)
            ->with('status', __('Contact removed.'));
    }
}
