<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcademicProgram extends Model
{
    use SoftDeletes;

    protected $table = 'programs';

    protected $fillable = [
        'faculty_id', 'name', 'code', 'degree_name', 'professional_title', 'modality', 'is_active'
    ];

    public function faculty()
    {
        return $this->belongsTo(Faculty::class);
    }

    public function studyPlans()
    {
        return $this->hasMany(StudyPlan::class, 'program_id');
    }

    public function accreditationCycles()
    {
        return $this->hasMany(AccreditationCycle::class, 'program_id');
    }
}
