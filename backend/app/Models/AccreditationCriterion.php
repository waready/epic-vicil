<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccreditationCriterion extends Model
{
    protected $fillable = ['accreditation_model_id', 'code', 'name', 'description', 'order', 'is_active'];

    public function accreditationModel()
    {
        return $this->belongsTo(AccreditationModel::class);
    }

    public function subcriteria()
    {
        return $this->hasMany(AccreditationSubcriterion::class);
    }

    public function requirements()
    {
        return $this->hasMany(EvidenceRequirement::class);
    }
}
