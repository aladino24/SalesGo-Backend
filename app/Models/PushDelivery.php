<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushDelivery extends Model
{
    protected $fillable = ['notification_id', 'device_id', 'status', 'attempts', 'provider_response', 'delivered_at', 'failed_at'];

    protected $casts = ['provider_response' => 'array', 'delivered_at' => 'datetime', 'failed_at' => 'datetime'];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(AppNotification::class, 'notification_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(PushDevice::class, 'device_id');
    }
}
