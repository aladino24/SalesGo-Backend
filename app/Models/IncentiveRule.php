<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncentiveRule extends Model
{
    protected $fillable = ['branch_id', 'sales_id', 'name', 'minimum_achievement_percent', 'incentive_rate_percent', 'fixed_amount', 'is_active'];

    protected $casts = ['minimum_achievement_percent' => 'decimal:2', 'incentive_rate_percent' => 'decimal:2', 'fixed_amount' => 'decimal:2', 'is_active' => 'boolean'];
}
