@extends('signer::portal.layout')

@section('title', __('Dashboard'))

@section('content')
    <h1 class="text-2xl font-semibold mb-6">{{ __('Dashboard') }}</h1>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <p class="text-sm text-gray-500">{{ __('Customers') }}</p>
            <p class="text-3xl font-semibold">{{ $customerCount }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <p class="text-sm text-gray-500">{{ __('Awaiting signatures') }}</p>
            <p class="text-3xl font-semibold">{{ $pendingCount }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <p class="text-sm text-gray-500">{{ __('Completed') }}</p>
            <p class="text-3xl font-semibold">{{ $completedCount }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="font-semibold">{{ __('Recent documents') }}</h2>
            <a href="{{ route('signer.portal.documents.create') }}"
               class="bg-gray-900 text-white text-sm rounded-lg px-4 py-2 hover:bg-gray-800">{{ __('New document') }}</a>
        </div>

        @forelse ($recentDocuments as $document)
            <a href="{{ route('signer.portal.documents.show', $document) }}"
               class="flex items-center justify-between px-6 py-3 border-b border-gray-100 hover:bg-gray-50">
                <div>
                    <p class="font-medium">{{ $document->title }}</p>
                    <p class="text-sm text-gray-500">{{ $document->customer?->name ?? __('No customer') }}</p>
                </div>
                @include('signer::portal.documents._status', ['status' => $document->status])
            </a>
        @empty
            <p class="px-6 py-8 text-gray-500">{{ __('No documents yet.') }}</p>
        @endforelse
    </div>
@endsection
