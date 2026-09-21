<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesTarget extends Model
{
    protected $fillable = ['branch_id', 'sales_id', 'period', 'revenue_target'];

    protected $casts = ['period' => 'date', 'revenue_target' => 'decimal:2'];
}
