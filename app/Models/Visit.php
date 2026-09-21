<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visit extends Model
{
    protected $fillable = ['client_visit_id', 'branch_id', 'outlet_id', 'sales_id', 'journey_id', 'planned_for', 'is_required', 'status', 'distance_km', 'latitude', 'longitude', 'checked_in_at', 'checked_out_at', 'notes', 'metadata'];

    protected $casts = ['distance_km' => 'float', 'latitude' => 'float', 'longitude' => 'float', 'planned_for' => 'date', 'is_required' => 'boolean', 'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'metadata' => 'array'];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function journey()
    {
        return $this->belongsTo(Journey::class);
    }
}
