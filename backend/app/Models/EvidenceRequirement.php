<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvidenceRequirement extends Model
{
    protected $fillable = [
        'accreditation_criterion_id', 'accreditation_subcriterion_id', 'code', 'name', 'description', 'applies_to',
        'evidence_kind', 'is_required', 'allows_multiple_files', 'allowed_extensions', 'order', 'is_active'
    ];

    protected $casts = ['allowed_extensions' => 'array', 'is_required' => 'boolean', 'allows_multiple_files' => 'boolean', 'is_active' => 'boolean'];

    public function criterion()
    {
        return $this->belongsTo(AccreditationCriterion::class, 'accreditation_criterion_id');
    }

    public function subcriterion()
    {
        return $this->belongsTo(AccreditationSubcriterion::class, 'accreditation_subcriterion_id');
    }
}
