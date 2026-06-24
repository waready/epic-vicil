<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseOffering extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'program_id', 'academic_term_id', 'course_id', 'section', 'group_code', 'enrolled_count', 'status',
        'is_assessment_course', 'assessment_result_code', 'assessment_result_name', 'requires_assessment_video',
    ];

    protected $casts = [
        'is_assessment_course' => 'boolean',
        'requires_assessment_video' => 'boolean',
    ];

    public function program()
    {
        return $this->belongsTo(AcademicProgram::class, 'program_id');
    }

    public function term()
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function course()
    {
        return $this->belongsTo(CurriculumCourse::class, 'course_id');
    }

    public function assignments()
    {
        return $this->hasMany(CourseAssignment::class);
    }

    public function mainAssignment()
    {
        return $this->hasOne(CourseAssignment::class)->where('role', 'main');
    }
}
