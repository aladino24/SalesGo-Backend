<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionUsage extends Model
{
    protected $fillable = ['promotion_id', 'branch_id', 'outlet_id', 'sales_id', 'outlet_transaction_id', 'reference', 'quantity', 'benefit_amount', 'status', 'reserved_at', 'consumed_at', 'released_at'];

    protected $casts = ['benefit_amount' => 'decimal:2', 'reserved_at' => 'datetime', 'consumed_at' => 'datetime', 'released_at' => 'datetime'];
}
