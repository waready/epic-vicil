<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'short_name', 'ruc', 'website', 'is_active'];

    public function faculties()
    {
        return $this->hasMany(Faculty::class);
    }
}
