<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvidenceAccessDelegation extends Model
{
    protected $fillable = [
        'delegate_user_id',
        'source_user_id',
        'reason',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_user_id');
    }

    public function source()
    {
        return $this->belongsTo(User::class, 'source_user_id');
    }
}
