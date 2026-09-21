<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = ['branch_id', 'product_id', 'code', 'name', 'description', 'terms', 'image_url', 'type', 'program_type', 'status', 'value', 'maximum_discount', 'minimum_order_amount', 'eligibility_rules', 'benefit_rules', 'stacking_rules', 'quota_total', 'quota_per_outlet', 'used_quota', 'budget_amount', 'used_budget', 'starts_at', 'ends_at', 'is_active'];

    protected $casts = [
        'value' => 'decimal:2',
        'maximum_discount' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'eligibility_rules' => 'array',
        'benefit_rules' => 'array',
        'stacking_rules' => 'array',
        'budget_amount' => 'decimal:2',
        'used_budget' => 'decimal:2',
    ];
}
