@extends('signer::portal.layout')

@section('title', __('New customer'))

@section('content')
    <h1 class="text-2xl font-semibold mb-6">{{ __('New customer') }}</h1>

    <form method="POST" action="{{ route('signer.portal.customers.store') }}"
          class="bg-white rounded-xl border border-gray-200 p-6">
        @csrf

        @include('signer::portal.customers._form')

        <div class="mt-6 flex gap-3">
            <button class="bg-gray-900 text-white rounded-lg px-4 py-2 text-sm hover:bg-gray-800">
                {{ __('Save') }}
            </button>
            <a href="{{ route('signer.portal.customers.index') }}"
               class="rounded-lg px-4 py-2 text-sm border border-gray-300 hover:bg-gray-50">{{ __('Cancel') }}</a>
        </div>
    </form>
@endsection
