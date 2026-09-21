<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitActivity extends Model
{
    protected $fillable = ['branch_id', 'outlet_id', 'visit_id', 'sales_id', 'activity', 'description', 'location', 'metadata', 'occurred_at'];

    protected $casts = ['location' => 'array', 'metadata' => 'array', 'occurred_at' => 'datetime'];
}
