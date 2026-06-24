<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvidenceReview extends Model
{
    protected $fillable = [
        'evidence_submission_id',
        'reviewer_id',
        'action',
        'comment',
        'from_status',
        'to_status',
    ];

    public function evidence()
    {
        return $this->belongsTo(EvidenceSubmission::class, 'evidence_submission_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
