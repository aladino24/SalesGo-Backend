<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTransaction extends Model
{
    protected $fillable = ['client_payment_id', 'payment_number', 'branch_id', 'outlet_id', 'received_by', 'amount', 'allocated_amount', 'unallocated_amount', 'status', 'source', 'latitude', 'longitude', 'accuracy_meters', 'occurred_at', 'submitted_at', 'verified_at', 'verified_by', 'notes', 'metadata'];
    protected $casts = ['amount' => 'decimal:2', 'allocated_amount' => 'decimal:2', 'unallocated_amount' => 'decimal:2', 'latitude' => 'float', 'longitude' => 'float', 'accuracy_meters' => 'float', 'occurred_at' => 'datetime', 'submitted_at' => 'datetime', 'verified_at' => 'datetime', 'metadata' => 'array'];

    public function components(): HasMany { return $this->hasMany(PaymentComponent::class); }
    public function allocations(): HasMany { return $this->hasMany(PaymentAllocation::class); }
    public function outlet(): BelongsTo { return $this->belongsTo(Outlet::class); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'received_by'); }
}
