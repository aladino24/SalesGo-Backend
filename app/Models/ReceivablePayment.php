<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceivablePayment extends Model
{
    protected $fillable = ['invoice_id', 'branch_id', 'outlet_id', 'received_by', 'amount', 'paid_at', 'reference_number', 'notes'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
}
