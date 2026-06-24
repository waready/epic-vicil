<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccreditationCycle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'accreditation_model_id', 'program_id', 'academic_term_id', 'name', 'year', 'starts_on', 'ends_on', 'status', 'settings'
    ];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'settings' => 'array'];

    public function model()
    {
        return $this->belongsTo(AccreditationModel::class, 'accreditation_model_id');
    }

    public function program()
    {
        return $this->belongsTo(AcademicProgram::class, 'program_id');
    }

    public function term()
    {
        return $this->belongsTo(AcademicTerm::class, 'academic_term_id');
    }

    public function tasks()
    {
        return $this->hasMany(EvidenceTask::class);
    }
}
