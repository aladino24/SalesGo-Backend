<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['branch_id', 'sales_division_id', 'branch_code', 'sku', 'name', 'brand', 'variant', 'size', 'uom', 'units_per_case', 'barcode', 'category', 'price', 'stock', 'reserved_stock', 'image_url', 'image_path', 'is_active'];

    protected $casts = ['price' => 'decimal:2', 'stock' => 'integer', 'reserved_stock' => 'integer', 'units_per_case' => 'integer', 'is_active' => 'boolean'];

    public function division()
    {
        return $this->belongsTo(SalesDivision::class, 'sales_division_id');
    }

    public function uoms(): HasMany
    {
        return $this->hasMany(ProductUom::class)->orderByDesc('is_default')->orderBy('conversion_to_base');
    }
}
