<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvidenceStatusHistory extends Model
{
    protected $fillable = ['evidence_task_id', 'evidence_submission_id', 'changed_by', 'from_status', 'to_status', 'comment'];
}
