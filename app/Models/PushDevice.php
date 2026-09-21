<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushDevice extends Model
{
    protected $fillable = ['user_id', 'branch_id', 'platform', 'token', 'token_hash', 'last_seen_at', 'disabled_at'];

    protected $casts = ['last_seen_at' => 'datetime', 'disabled_at' => 'datetime'];
}
