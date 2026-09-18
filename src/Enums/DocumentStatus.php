<?php

namespace Darvis\Signer\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
