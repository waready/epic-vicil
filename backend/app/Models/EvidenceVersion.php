<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvidenceVersion extends Model
{
    protected $fillable = [
        'evidence_submission_id',
        'file_asset_id',
        'version_number',
        'change_summary',
        'uploaded_by',
    ];

    public function evidence()
    {
        return $this->belongsTo(EvidenceSubmission::class, 'evidence_submission_id');
    }

    public function file()
    {
        return $this->belongsTo(FileAsset::class, 'file_asset_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
