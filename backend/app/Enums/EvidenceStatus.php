<?php

namespace App\Enums;

enum EvidenceStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case Uploaded = 'uploaded';
    case InReview = 'in_review';
    case Observed = 'observed';
    case Corrected = 'corrected';
    case Validated = 'validated';
    case Approved = 'approved';
    case ReadyToExport = 'ready_to_export';
    case Archived = 'archived';
}
