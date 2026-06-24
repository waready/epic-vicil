<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'institution_id', 'document_type', 'document_number', 'first_name', 'last_name', 'email', 'phone',
        'highest_degree', 'specialty', 'employment_type', 'is_active', 'profile_data'
    ];

    protected $casts = ['profile_data' => 'array', 'is_active' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function assignments()
    {
        return $this->hasMany(CourseAssignment::class);
    }
}
