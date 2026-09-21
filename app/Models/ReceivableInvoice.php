<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceivableInvoice extends Model
{
    protected $fillable = ['branch_id', 'outlet_id', 'sales_order_id', 'invoice_number', 'invoice_date', 'due_date', 'original_amount', 'paid_amount'];

    protected $casts = ['invoice_date' => 'date', 'due_date' => 'date', 'original_amount' => 'decimal:2', 'paid_amount' => 'decimal:2'];

    public function payments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class, 'invoice_id');
    }
}
