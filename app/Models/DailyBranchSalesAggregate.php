<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyBranchSalesAggregate extends Model
{
    protected $fillable = ['branch_id', 'date', 'committed_revenue', 'committed_order_count', 'active_outlet_count', 'generated_at'];

    protected $casts = ['date' => 'date', 'committed_revenue' => 'decimal:2', 'generated_at' => 'datetime'];
}
