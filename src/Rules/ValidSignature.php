<?php

namespace Darvis\Signer\Rules;

use Closure;
use Darvis\Signer\Support\SignatureDataUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class ValidSignature implements ValidationRule
{
    public function __construct(
        protected int $maxKilobytes,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            SignatureDataUrl::decode(is_string($value) ? $value : '', $this->maxKilobytes);
        } catch (InvalidArgumentException) {
            $fail(__('The signature could not be read. Please draw it again.'));
        }
    }
}
