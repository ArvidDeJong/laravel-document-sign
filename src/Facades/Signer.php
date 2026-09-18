<?php

namespace Darvis\Signer\Facades;

use Darvis\Signer\Services\DocumentBuilder;
use Illuminate\Support\Facades\Facade;

/**
 * @method static DocumentBuilder document(string $pdfPath)
 *
 * @see \Darvis\Signer\Services\SignerManager
 */
class Signer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'signer';
    }
}
