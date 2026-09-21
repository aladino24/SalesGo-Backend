<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportArchive extends Model
{
    protected $fillable = ['branch_id', 'period_start', 'period_end', 'payload', 'archived_at', 'expires_at'];

    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'payload' => 'array', 'archived_at' => 'datetime', 'expires_at' => 'datetime'];
}
