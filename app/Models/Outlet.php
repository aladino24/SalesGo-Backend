<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    protected $fillable = ['branch_id', 'branch_code', 'code', 'name', 'address', 'type', 'owner_name', 'contact_name', 'phone', 'photo_attachment_id', 'latitude', 'longitude', 'geofence_radius_meters', 'sales_responsible_id', 'status'];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float', 'geofence_radius_meters' => 'integer'];

    public function photoAttachment()
    {
        return $this->belongsTo(Attachment::class, 'photo_attachment_id');
    }

    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(SalesDivision::class, 'outlet_sales_division')->withTimestamps();
    }

    public function routeAssignments(): HasMany
    {
        return $this->hasMany(OutletRouteAssignment::class);
    }
}
