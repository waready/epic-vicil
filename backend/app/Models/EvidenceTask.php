<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvidenceTask extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'accreditation_cycle_id', 'program_id', 'accreditation_criterion_id', 'accreditation_subcriterion_id',
        'evidence_requirement_id', 'academic_year_id', 'academic_term_id', 'context_type', 'context_id',
        'assigned_to', 'created_by', 'due_date', 'status', 'priority', 'instructions', 'metadata'
    ];

    protected $casts = ['due_date' => 'date', 'metadata' => 'array'];

    public function cycle()
    {
        return $this->belongsTo(AccreditationCycle::class, 'accreditation_cycle_id');
    }

    public function program()
    {
        return $this->belongsTo(AcademicProgram::class, 'program_id');
    }

    public function criterion()
    {
        return $this->belongsTo(AccreditationCriterion::class, 'accreditation_criterion_id');
    }

    public function requirement()
    {
        return $this->belongsTo(EvidenceRequirement::class, 'evidence_requirement_id');
    }

    public function subcriterion()
    {
        return $this->belongsTo(AccreditationSubcriterion::class, 'accreditation_subcriterion_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function submissions()
    {
        return $this->hasMany(EvidenceSubmission::class);
    }

    public function currentSubmission()
    {
        return $this->hasOne(EvidenceSubmission::class)->latestOfMany();
    }

    public function histories()
    {
        return $this->hasMany(EvidenceStatusHistory::class);
    }

    public function courseOfferingContext()
    {
        return $this->belongsTo(CourseOffering::class, 'context_id');
    }

    public function teacherContext()
    {
        return $this->belongsTo(Teacher::class, 'context_id');
    }
}
