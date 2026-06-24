<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccreditationModel extends Model
{
    protected $fillable = ['name', 'code', 'level', 'description', 'is_active', 'metadata'];

    protected $casts = ['metadata' => 'array', 'is_active' => 'boolean'];

    public function criteria()
    {
        return $this->hasMany(AccreditationCriterion::class);
    }
}
