<?php

namespace Darvis\Signer\Exceptions;

use Darvis\Signer\Models\Signer;
use RuntimeException;
use Throwable;

/**
 * Storing or stamping a signature failed and everything was rolled back, so
 * the signer can submit again. The cause is the previous exception.
 */
class SigningFailed extends RuntimeException
{
    public static function for(Signer $signer, Throwable $previous): self
    {
        return new self(
            "Signing failed for signer [{$signer->uuid}] and was rolled back: {$previous->getMessage()}",
            0,
            $previous,
        );
    }
}
