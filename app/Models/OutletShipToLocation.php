<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletShipToLocation extends Model
{
    protected $fillable = [
        'branch_id', 'outlet_id', 'code', 'name', 'address', 'contact_name',
        'phone', 'latitude', 'longitude', 'is_default', 'is_active',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
