<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUom extends Model
{
    protected $table = 'product_uoms';

    protected $fillable = [
        'product_id',
        'code',
        'name',
        'conversion_to_base',
        'price',
        'minimum_quantity',
        'maximum_quantity',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'conversion_to_base' => 'integer',
        'price' => 'decimal:2',
        'minimum_quantity' => 'integer',
        'maximum_quantity' => 'integer',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
