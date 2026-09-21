<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $fillable = ['branch_id', 'type', 'entity_type', 'entity_id', 'requested_by', 'approver_id', 'reason', 'comment', 'status', 'decided_at'];

    protected $casts = ['decided_at' => 'datetime'];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
