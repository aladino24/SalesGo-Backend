<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionMerchandisingProof extends Model
{
    protected $fillable = ['promotion_id', 'branch_id', 'outlet_id', 'submitted_by', 'attachment_ids', 'notes', 'latitude', 'longitude', 'status', 'verified_by', 'verified_at', 'verification_note'];

    protected $casts = ['attachment_ids' => 'array', 'latitude' => 'float', 'longitude' => 'float', 'verified_at' => 'datetime'];
}
