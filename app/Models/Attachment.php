<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable = ['client_uuid', 'branch_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'status', 'sha256', 'error_message', 'finalized_at'];

    protected $casts = ['finalized_at' => 'datetime'];
}
