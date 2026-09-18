@extends('signer::portal.layout')

@section('title', __('New document'))

@section('content')
    <h1 class="text-2xl font-semibold mb-6">{{ __('New document') }}</h1>

    <form method="POST" action="{{ route('signer.portal.documents.store') }}" enctype="multipart/form-data"
          class="space-y-6">
        @csrf

        <div class="bg-white rounded-xl border border-gray-200 p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1" for="title">{{ __('Title') }}</label>
                <input id="title" name="title" value="{{ old('title') }}" required
                       class="w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="customer_id">{{ __('Customer') }}</label>
                <select id="customer_id" name="customer_id"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    <option value="">{{ __('No customer') }}</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>
                            {{ $customer->name }}{{ $customer->company ? ' ('.$customer->company.')' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1" for="pdf">{{ __('PDF file') }}</label>
                <input id="pdf" name="pdf" type="file" accept="application/pdf" required
                       class="w-full rounded-lg border border-gray-300 px-3 py-2 bg-white">
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold">{{ __('Signers') }}</h2>
                <div class="flex gap-3">
                    <button type="button" id="fill-contacts"
                            class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 hover:bg-gray-50">
                        {{ __('Prefill from customer contacts') }}
                    </button>
                    <button type="button" id="add-signer"
                            class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 hover:bg-gray-50">
                        {{ __('Add signer') }}
                    </button>
                </div>
            </div>

            <p class="text-sm text-gray-500 mb-4">
                {{ __('Position values are percentages of the page, measured from the top left corner.') }}
            </p>

            <div id="signer-rows" class="space-y-3"></div>
        </div>

        <button class="bg-gray-900 text-white rounded-lg px-6 py-2.5 font-medium hover:bg-gray-800">
            {{ __('Send for signing') }}
        </button>
    </form>

    <template id="signer-row-template">
        <div class="grid grid-cols-12 gap-3 items-end signer-row">
            <div class="col-span-3">
                <label class="block text-xs text-gray-500 mb-1">{{ __('Name') }}</label>
                <input data-field="name" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="col-span-3">
                <label class="block text-xs text-gray-500 mb-1">{{ __('Email address') }}</label>
                <input data-field="email" type="email" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="col-span-1">
                <label class="block text-xs text-gray-500 mb-1">{{ __('Page') }}</label>
                <input data-field="page" type="number" min="1" value="1" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="col-span-2">
                <label class="block text-xs text-gray-500 mb-1">X %</label>
                <input data-field="x" type="number" min="0" max="100" step="0.1" value="10" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Y %</label>
                <input data-field="y" type="number" min="0" max="100" step="0.1" value="80" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
            </div>
            <div class="col-span-1">
                <button type="button" class="remove-signer text-sm text-red-600 underline pb-2">{{ __('Remove') }}</button>
            </div>
        </div>
    </template>

    <script>
        (function () {
            const customers = @json($customers->mapWithKeys(fn ($c) => [$c->id => $c->contacts->map(fn ($contact) => ['name' => $contact->name, 'email' => $contact->email])]));
            const rows = document.getElementById('signer-rows');
            const template = document.getElementById('signer-row-template');
            let index = 0;

            function addRow(prefill = {}) {
                const row = template.content.cloneNode(true).querySelector('.signer-row');

                row.querySelectorAll('[data-field]').forEach(function (input) {
                    const field = input.dataset.field;
                    input.name = 'signers[' + index + '][' + field + ']';
                    if (prefill[field] !== undefined) input.value = prefill[field];
                });

                row.querySelector('.remove-signer').addEventListener('click', function () {
                    row.remove();
                });

                rows.appendChild(row);
                index++;
            }

            document.getElementById('add-signer').addEventListener('click', function () {
                addRow();
            });

            document.getElementById('fill-contacts').addEventListener('click', function () {
                const customerId = document.getElementById('customer_id').value;
                (customers[customerId] || []).forEach(addRow);
            });

            addRow();
        })();
    </script>
@endsection
