@extends('signer::portal.layout')

@section('title', __('Customers'))

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">{{ __('Customers') }}</h1>
        <a href="{{ route('signer.portal.customers.create') }}"
           class="bg-gray-900 text-white text-sm rounded-lg px-4 py-2 hover:bg-gray-800">{{ __('New customer') }}</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Company') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Email address') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Contacts') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Documents') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium">{{ $customer->name }}</td>
                        <td class="px-6 py-3">{{ $customer->company }}</td>
                        <td class="px-6 py-3">{{ $customer->email }}</td>
                        <td class="px-6 py-3">{{ $customer->contacts_count }}</td>
                        <td class="px-6 py-3">{{ $customer->documents_count }}</td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('signer.portal.customers.edit', $customer) }}"
                               class="text-gray-900 underline">{{ __('Edit') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-gray-500">{{ __('No customers yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $customers->links() }}</div>
@endsection
