<?php

namespace Darvis\Signer\Http\Controllers\Portal;

use Darvis\Signer\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        return view('signer::portal.customers.index', [
            'customers' => Customer::withCount(['contacts', 'documents'])->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('signer::portal.customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::create($this->validated($request));

        return redirect()
            ->route('signer.portal.customers.edit', $customer)
            ->with('status', __('Customer created.'));
    }

    public function edit(Customer $customer): View
    {
        return view('signer::portal.customers.edit', [
            'customer' => $customer->load('contacts'),
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request));

        return redirect()
            ->route('signer.portal.customers.edit', $customer)
            ->with('status', __('Customer updated.'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()
            ->route('signer.portal.customers.index')
            ->with('status', __('Customer deleted.'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
