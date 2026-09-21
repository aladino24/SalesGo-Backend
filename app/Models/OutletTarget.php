<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutletTarget extends Model
{
    protected $fillable = ['branch_id', 'outlet_id', 'period', 'revenue_target'];

    protected $casts = ['period' => 'date', 'revenue_target' => 'decimal:2'];
}
