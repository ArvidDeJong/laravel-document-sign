@php($customer = $customer ?? null)

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1" for="name">{{ __('Name') }}</label>
        <input id="name" name="name" value="{{ old('name', $customer?->name) }}" required
               class="w-full rounded-lg border border-gray-300 px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1" for="company">{{ __('Company') }}</label>
        <input id="company" name="company" value="{{ old('company', $customer?->company) }}"
               class="w-full rounded-lg border border-gray-300 px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1" for="email">{{ __('Email address') }}</label>
        <input id="email" name="email" type="email" value="{{ old('email', $customer?->email) }}"
               class="w-full rounded-lg border border-gray-300 px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1" for="phone">{{ __('Phone') }}</label>
        <input id="phone" name="phone" value="{{ old('phone', $customer?->phone) }}"
               class="w-full rounded-lg border border-gray-300 px-3 py-2">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1" for="address">{{ __('Address') }}</label>
        <input id="address" name="address" value="{{ old('address', $customer?->address) }}"
               class="w-full rounded-lg border border-gray-300 px-3 py-2">
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1" for="postal_code">{{ __('Postal code') }}</label>
            <input id="postal_code" name="postal_code" value="{{ old('postal_code', $customer?->postal_code) }}"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1" for="city">{{ __('City') }}</label>
            <input id="city" name="city" value="{{ old('city', $customer?->city) }}"
                   class="w-full rounded-lg border border-gray-300 px-3 py-2">
        </div>
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium mb-1" for="notes">{{ __('Notes') }}</label>
        <textarea id="notes" name="notes" rows="3"
                  class="w-full rounded-lg border border-gray-300 px-3 py-2">{{ old('notes', $customer?->notes) }}</textarea>
    </div>
</div>
