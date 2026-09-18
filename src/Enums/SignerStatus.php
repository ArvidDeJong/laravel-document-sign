<?php

namespace Darvis\Signer\Enums;

enum SignerStatus: string
{
    case Pending = 'pending';
    case Signed = 'signed';
    case Declined = 'declined';
}
