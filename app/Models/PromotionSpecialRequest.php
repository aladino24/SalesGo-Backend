<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionSpecialRequest extends Model
{
    protected $fillable = ['branch_id', 'outlet_id', 'requested_by', 'promotion_id', 'product_id', 'requested_type', 'requested_value', 'requested_quantity', 'potential_revenue', 'reason', 'attachment_ids', 'starts_at', 'ends_at', 'status'];

    protected $casts = ['requested_value' => 'decimal:2', 'potential_revenue' => 'decimal:2', 'attachment_ids' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
