<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyRecord extends Model
{
    protected $fillable = ['user_id', 'idempotency_key', 'endpoint', 'request_hash', 'status_code', 'response_body', 'expires_at'];

    protected $casts = ['response_body' => 'array', 'expires_at' => 'datetime'];
}
