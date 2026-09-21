<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesLocationPing extends Model
{
    protected $fillable = ['branch_id', 'sales_id', 'latitude', 'longitude', 'accuracy_meters', 'source', 'recorded_at'];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float', 'accuracy_meters' => 'float', 'recorded_at' => 'datetime'];
}
