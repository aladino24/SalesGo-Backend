<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    protected $fillable = ['branch_id', 'created_by', 'meeting_code', 'title', 'description', 'starts_at', 'ends_at', 'status', 'provider', 'join_url', 'agenda'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'agenda' => 'array'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
