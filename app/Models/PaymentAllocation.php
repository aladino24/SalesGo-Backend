<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    protected $fillable = ['payment_transaction_id', 'invoice_id', 'amount', 'status'];
    protected $casts = ['amount' => 'decimal:2'];
    public function payment(): BelongsTo { return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(ReceivableInvoice::class, 'invoice_id'); }
}
