<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CollectionActivity extends Model
{
    protected $fillable = ['client_activity_id', 'branch_id', 'outlet_id', 'created_by', 'type', 'status', 'reason', 'promise_date', 'promised_amount', 'invoice_ids', 'latitude', 'longitude', 'notes', 'metadata'];
    protected $casts = ['promise_date' => 'date', 'promised_amount' => 'decimal:2', 'invoice_ids' => 'array', 'metadata' => 'array'];
}
