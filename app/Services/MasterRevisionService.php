<?php

namespace App\Services;

use App\Models\ImportantFile;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Support\Carbon;

class MasterRevisionService
{
    /** @return array{revision: string, generatedAt: string} */
    public function forBranch(int $branchId): array
    {
        $datasets = [Product::class, Outlet::class, Promotion::class, ImportantFile::class];
        $parts = [$branchId];
        $latest = null;
        foreach ($datasets as $model) {
            $query = $model::where('branch_id', $branchId);
            $updatedAt = $query->max('updated_at');
            $parts[] = $model.':'.$query->count().':'.($updatedAt ?? '');
            if ($updatedAt && (! $latest || Carbon::parse($updatedAt)->gt($latest))) {
                $latest = Carbon::parse($updatedAt);
            }
        }
        $generatedAt = $latest ?? now();

        return ['revision' => 'master-'.sha1(implode('|', $parts)), 'generatedAt' => $generatedAt->toIso8601String()];
    }
}
