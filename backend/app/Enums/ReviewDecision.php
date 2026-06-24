<?php

namespace App\Enums;

enum ReviewDecision: string
{
    case Observed = 'observed';
    case Validated = 'validated';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
