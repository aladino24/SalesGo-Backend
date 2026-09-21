<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryNote extends Model
{
    protected $fillable = ['branch_id', 'journey_id', 'created_by', 'number', 'destination', 'items', 'status', 'approval_status', 'stock_reserved_at', 'stock_released_at', 'stock_consumed_at', 'used_at'];

    protected $casts = ['items' => 'array', 'stock_reserved_at' => 'datetime', 'stock_released_at' => 'datetime', 'stock_consumed_at' => 'datetime', 'used_at' => 'datetime'];
}
