@extends('signer::portal.layout')

@section('title', __('Documents'))

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">{{ __('Documents') }}</h1>
        <a href="{{ route('signer.portal.documents.create') }}"
           class="bg-gray-900 text-white text-sm rounded-lg px-4 py-2 hover:bg-gray-800">{{ __('New document') }}</a>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-gray-500">
                <tr>
                    <th class="px-6 py-3 font-medium">{{ __('Title') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Customer') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Signers') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Created') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr class="border-t border-gray-100 hover:bg-gray-50 cursor-pointer"
                        onclick="window.location='{{ route('signer.portal.documents.show', $document) }}'">
                        <td class="px-6 py-3 font-medium">{{ $document->title }}</td>
                        <td class="px-6 py-3">{{ $document->customer?->name }}</td>
                        <td class="px-6 py-3">
                            {{ $document->signers->where('status', \Darvis\Signer\Enums\SignerStatus::Signed)->count() }}
                            / {{ $document->signers->count() }}
                        </td>
                        <td class="px-6 py-3">
                            @include('signer::portal.documents._status', ['status' => $document->status])
                        </td>
                        <td class="px-6 py-3 text-gray-500">{{ $document->created_at->format('d-m-Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-gray-500">{{ __('No documents yet.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $documents->links() }}</div>
@endsection
