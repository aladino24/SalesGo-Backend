<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppRelease extends Model
{
    protected $fillable = [
        'platform',
        'version_name',
        'version_code',
        'md5',
        'sha256',
        'disk',
        'path',
        'original_name',
        'size_bytes',
        'release_notes',
        'is_mandatory',
        'is_published',
        'uploaded_by',
        'published_at',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
