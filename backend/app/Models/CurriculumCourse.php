<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CurriculumCourse extends Model
{
    use SoftDeletes;

    protected $table = 'courses';

    protected $fillable = [
        'study_plan_id', 'code', 'name', 'cycle_number', 'credits', 'theory_hours', 'practice_hours', 'lab_hours', 'is_required', 'is_active'
    ];

    public function studyPlan()
    {
        return $this->belongsTo(StudyPlan::class);
    }

    public function offerings()
    {
        return $this->hasMany(CourseOffering::class, 'course_id');
    }
}
