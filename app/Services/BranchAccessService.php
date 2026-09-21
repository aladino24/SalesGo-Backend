<?php

namespace App\Services;

use App\Models\User;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class BranchAccessService
{
    /** @return array<int, int> */
    public function allowedBranchIds(User $user): array
    {
        $user->loadMissing('role');
        if ($user->role->slug === 'it') {
            return Branch::query()->pluck('id')->map(fn ($branchId) => (int) $branchId)->all();
        }
        if ($user->role->slug !== 'branchManager') {
            return [$user->branch_id];
        }

        return collect([$user->branch_id])
            ->merge(DB::table('user_branch_access')->where('user_id', $user->id)->pluck('branch_id'))
            ->map(fn ($branchId) => (int) $branchId)
            ->unique()
            ->values()
            ->all();
    }
}
