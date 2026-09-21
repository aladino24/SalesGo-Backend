<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['branch_id', 'role_id', 'sales_division_id', 'employee_code', 'increment_no', 'name', 'username', 'email', 'password', 'is_active', 'max_devices'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['password' => 'hashed', 'is_active' => 'boolean', 'max_devices' => 'integer'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function division()
    {
        return $this->belongsTo(SalesDivision::class, 'sales_division_id');
    }

    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(SalesDivision::class, 'sales_division_user')->withTimestamps();
    }

    public function locationPings(): HasMany
    {
        return $this->hasMany(SalesLocationPing::class, 'sales_id');
    }

    public function lastLocation(): HasOne
    {
        return $this->hasOne(SalesLocationPing::class, 'sales_id')->latestOfMany('recorded_at');
    }

    public function pushDevices(): HasMany
    {
        return $this->hasMany(PushDevice::class);
    }

    public function deviceSessions(): HasMany
    {
        return $this->hasMany(UserDeviceSession::class);
    }
}
