@php
    $classes = match ($status) {
        \Darvis\Signer\Enums\DocumentStatus::Completed => 'bg-green-100 text-green-800',
        \Darvis\Signer\Enums\DocumentStatus::Pending => 'bg-amber-100 text-amber-800',
        \Darvis\Signer\Enums\DocumentStatus::Cancelled => 'bg-red-100 text-red-800',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp

<span class="text-xs font-medium px-2.5 py-1 rounded-full {{ $classes }}">
    {{ __($status->value) }}
</span>
