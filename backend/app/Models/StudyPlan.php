<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudyPlan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'program_id', 'name', 'code', 'year', 'approved_on', 'approval_document', 'is_current', 'is_active'
    ];

    protected $casts = ['approved_on' => 'date', 'is_current' => 'boolean', 'is_active' => 'boolean'];

    public function program()
    {
        return $this->belongsTo(AcademicProgram::class, 'program_id');
    }

    public function courses()
    {
        return $this->hasMany(CurriculumCourse::class);
    }
}
