<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Journey extends Model
{
    protected $fillable = ['client_journey_id', 'branch_id', 'sales_id', 'type', 'destination', 'starts_at', 'ends_at', 'actual_started_at', 'actual_completed_at', 'status', 'approval_status', 'reason'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'actual_started_at' => 'datetime', 'actual_completed_at' => 'datetime'];
}
