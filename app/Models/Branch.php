<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['code', 'name', 'is_active', 'default_geofence_radius_meters'];

    protected $casts = ['is_active' => 'boolean', 'default_geofence_radius_meters' => 'integer'];
}
