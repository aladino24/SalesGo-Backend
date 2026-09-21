<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportantFile extends Model
{
    protected $fillable = ['branch_id', 'uploaded_by', 'name', 'type', 'disk', 'path', 'size_bytes', 'version', 'description'];

    protected $casts = ['size_bytes' => 'integer'];
}
