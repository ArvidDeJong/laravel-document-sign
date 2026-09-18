@extends('signer::portal.layout')

@section('title', __('Edit customer'))

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">{{ $customer->name }}</h1>

        <form method="POST" action="{{ route('signer.portal.customers.destroy', $customer) }}"
              onsubmit="return confirm('{{ __('Delete this customer?') }}')">
            @csrf
            @method('DELETE')
            <button class="text-sm text-red-600 underline">{{ __('Delete customer') }}</button>
        </form>
    </div>

    <form method="POST" action="{{ route('signer.portal.customers.update', $customer) }}"
          class="bg-white rounded-xl border border-gray-200 p-6 mb-8">
        @csrf
        @method('PUT')

        @include('signer::portal.customers._form')

        <div class="mt-6">
            <button class="bg-gray-900 text-white rounded-lg px-4 py-2 text-sm hover:bg-gray-800">
                {{ __('Save') }}
            </button>
        </div>
    </form>

    <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="font-semibold">{{ __('Contacts') }}</h2>
        </div>

        @forelse ($customer->contacts as $contact)
            <div class="flex items-center justify-between px-6 py-3 border-b border-gray-100">
                <div>
                    <p class="font-medium">{{ $contact->name }}</p>
                    <p class="text-sm text-gray-500">{{ $contact->email }} {{ $contact->phone ? '| '.$contact->phone : '' }}</p>
                </div>
                <form method="POST" action="{{ route('signer.portal.contacts.destroy', $contact) }}">
                    @csrf
                    @method('DELETE')
                    <button class="text-sm text-red-600 underline">{{ __('Remove') }}</button>
                </form>
            </div>
        @empty
            <p class="px-6 py-4 text-gray-500">{{ __('No contacts yet.') }}</p>
        @endforelse

        <form method="POST" action="{{ route('signer.portal.customers.contacts.store', $customer) }}"
              class="px-6 py-4 grid grid-cols-1 sm:grid-cols-4 gap-3 bg-gray-50 rounded-b-xl">
            @csrf
            <input name="name" placeholder="{{ __('Name') }}" required
                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <input name="email" type="email" placeholder="{{ __('Email address') }}" required
                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <input name="phone" placeholder="{{ __('Phone') }}"
                   class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
            <button class="bg-gray-900 text-white rounded-lg px-4 py-2 text-sm hover:bg-gray-800">
                {{ __('Add contact') }}
            </button>
        </form>
    </div>
@endsection
