<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletTransaction extends Model
{
    protected $fillable = ['client_transaction_id', 'document_number', 'branch_id', 'outlet_id', 'sales_id', 'type', 'status', 'approval_status', 'reason', 'amount', 'items', 'metadata', 'occurred_at', 'committed_at'];

    protected $casts = ['amount' => 'decimal:2', 'items' => 'array', 'metadata' => 'array', 'occurred_at' => 'datetime', 'committed_at' => 'datetime'];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }
}
