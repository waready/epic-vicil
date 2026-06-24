<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileAsset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uploaded_by',
        'disk',
        'path',
        'original_name',
        'stored_name',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum',
        'visibility',
        'metadata',
    ];

    protected $casts = ['metadata' => 'array'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function versions()
    {
        return $this->hasMany(EvidenceVersion::class);
    }
}
