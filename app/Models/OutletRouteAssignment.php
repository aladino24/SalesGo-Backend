<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutletRouteAssignment extends Model
{
    protected $fillable = ['branch_id', 'outlet_id', 'sales_id', 'day_of_week', 'week_of_month', 'is_active'];

    protected $casts = ['day_of_week' => 'integer', 'week_of_month' => 'integer', 'is_active' => 'boolean'];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }
}
