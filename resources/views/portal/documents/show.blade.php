@extends('signer::portal.layout')

@section('title', $document->title)

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">{{ $document->title }}</h1>
            <p class="text-gray-500">{{ $document->customer?->name ?? __('No customer') }}</p>
        </div>
        <div class="flex items-center gap-3">
            @include('signer::portal.documents._status', ['status' => $document->status])

            <a href="{{ route('signer.portal.documents.download', [$document, 'original']) }}"
               class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 hover:bg-gray-50">
                {{ __('Download original') }}
            </a>

            @if ($document->signed_path)
                <a href="{{ route('signer.portal.documents.download', [$document, 'signed']) }}"
                   class="text-sm bg-gray-900 text-white rounded-lg px-3 py-1.5 hover:bg-gray-800">
                    {{ __('Download signed PDF') }}
                </a>
            @endif

            <form method="POST" action="{{ route('signer.portal.documents.destroy', $document) }}"
                  onsubmit="return confirm('{{ __('Delete this document?') }}')">
                @csrf
                @method('DELETE')
                <button class="text-sm text-red-600 underline">{{ __('Delete') }}</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="font-semibold">{{ __('Signers') }}</h2>
            </div>
            @foreach ($document->signers as $signer)
                <div class="flex items-center justify-between px-6 py-3 border-b border-gray-100">
                    <div>
                        <p class="font-medium">{{ $signer->name }}</p>
                        <p class="text-sm text-gray-500">{{ $signer->email }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium">{{ __($signer->status->value) }}</p>
                        @if ($signer->signed_at)
                            <p class="text-xs text-gray-500">{{ $signer->signed_at->format('d-m-Y H:i') }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="font-semibold">{{ __('Audit trail') }}</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($document->auditEvents->sortByDesc('created_at') as $event)
                    <div class="px-6 py-3">
                        <p class="text-sm font-medium">{{ __($event->event) }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $event->created_at->format('d-m-Y H:i:s') }}
                            @if ($event->signer) | {{ $event->signer->name }} @endif
                            @if ($event->ip_address) | {{ $event->ip_address }} @endif
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
