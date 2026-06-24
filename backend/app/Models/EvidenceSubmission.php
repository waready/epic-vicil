<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EvidenceSubmission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'program_id',
        'accreditation_cycle_id',
        'accreditation_criterion_id',
        'accreditation_subcriterion_id',
        'evidence_requirement_id',
        'evidence_task_id',
        'course_id',
        'teacher_id',
        'current_file_asset_id',
        'title',
        'description',
        'status',
        'version_number',
        'submitted_by',
        'reviewed_by',
        'validated_by',
        'approved_by',
        'submitted_at',
        'reviewed_at',
        'validated_at',
        'approved_at',
        'metadata',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'validated_at' => 'datetime',
        'approved_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function task()
    {
        return $this->belongsTo(EvidenceTask::class, 'evidence_task_id');
    }

    public function program()
    {
        return $this->belongsTo(AcademicProgram::class, 'program_id');
    }

    public function cycle()
    {
        return $this->belongsTo(AccreditationCycle::class, 'accreditation_cycle_id');
    }

    public function criterion()
    {
        return $this->belongsTo(AccreditationCriterion::class, 'accreditation_criterion_id');
    }

    public function subcriterion()
    {
        return $this->belongsTo(AccreditationSubcriterion::class, 'accreditation_subcriterion_id');
    }

    public function requirement()
    {
        return $this->belongsTo(EvidenceRequirement::class, 'evidence_requirement_id');
    }

    public function course()
    {
        return $this->belongsTo(CurriculumCourse::class, 'course_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function currentFile()
    {
        return $this->belongsTo(FileAsset::class, 'current_file_asset_id');
    }

    public function versions()
    {
        return $this->hasMany(EvidenceVersion::class);
    }

    public function reviews()
    {
        return $this->hasMany(EvidenceReview::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
