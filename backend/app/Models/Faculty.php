<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Faculty extends Model
{
    use SoftDeletes;

    protected $fillable = ['institution_id', 'name', 'code', 'is_active'];

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function programs()
    {
        return $this->hasMany(AcademicProgram::class);
    }
}
