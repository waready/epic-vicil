<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccreditationSubcriterion extends Model
{
    protected $fillable = ['accreditation_criterion_id', 'code', 'name', 'description', 'order', 'is_active'];

    public function criterion()
    {
        return $this->belongsTo(AccreditationCriterion::class, 'accreditation_criterion_id');
    }
}
