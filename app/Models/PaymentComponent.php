<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentComponent extends Model
{
    protected $fillable = ['payment_transaction_id', 'method', 'amount', 'status', 'reference_number', 'details', 'attachment_ids'];
    protected $casts = ['amount' => 'decimal:2', 'details' => 'array', 'attachment_ids' => 'array'];
    public function payment(): BelongsTo { return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id'); }
}
