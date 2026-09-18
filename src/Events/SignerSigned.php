<?php

namespace Darvis\Signer\Events;

use Darvis\Signer\Models\Signer;
use Illuminate\Foundation\Events\Dispatchable;

class SignerSigned
{
    use Dispatchable;

    public function __construct(
        public Signer $signer,
    ) {}
}
