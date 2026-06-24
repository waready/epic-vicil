<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseAssignment extends Model
{
    protected $fillable = ['course_offering_id', 'teacher_id', 'role', 'weekly_hours'];

    public function courseOffering()
    {
        return $this->belongsTo(CourseOffering::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }
}
