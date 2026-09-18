<?php

namespace Darvis\Signer\Events;

use Darvis\Signer\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;

class DocumentCompleted
{
    use Dispatchable;

    public function __construct(
        public Document $document,
    ) {}
}
