<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\OutletTransaction;
use App\Models\Promotion;
use App\Models\PromotionUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function resolve(int $branchId, ?string $code): ?Promotion
    {
        if (blank($code)) {
            return null;
        }

        $promotion = Promotion::where('branch_id', $branchId)
            ->where('code', $code)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();
        if (! $promotion) {
            throw ValidationException::withMessages(['promotionCode' => 'Promo tidak tersedia atau telah berakhir.']);
        }

        return $promotion;
    }

    /** Validate the rules that must never be trusted from the mobile client. */
    public function assertEligible(Promotion $promotion, Outlet $outlet, User $user, float $grossAmount): void
    {
        $rules = $promotion->eligibility_rules ?? [];
        if ($grossAmount < (float) $promotion->minimum_order_amount) {
            throw ValidationException::withMessages(['promotionCode' => 'Minimal pembelian promosi belum terpenuhi.']);
        }
        if (($rules['outletIds'] ?? []) !== [] && ! in_array($outlet->id, array_map('intval', $rules['outletIds']), true)) {
            throw ValidationException::withMessages(['promotionCode' => 'Promosi ini tidak berlaku untuk outlet yang dipilih.']);
        }
        if (($rules['outletTypes'] ?? []) !== [] && ! in_array($outlet->type, $rules['outletTypes'], true)) {
            throw ValidationException::withMessages(['promotionCode' => 'Promosi tidak berlaku untuk jenis outlet ini.']);
        }
        if (($rules['roles'] ?? []) !== [] && ! in_array($user->role?->slug, $rules['roles'], true)) {
            throw ValidationException::withMessages(['promotionCode' => 'Peran pengguna tidak berhak memakai promosi ini.']);
        }
        if ($promotion->quota_total !== null && $promotion->used_quota >= $promotion->quota_total) {
            throw ValidationException::withMessages(['promotionCode' => 'Kuota promosi telah habis.']);
        }
        if ($promotion->quota_per_outlet !== null && PromotionUsage::where('promotion_id', $promotion->id)->where('outlet_id', $outlet->id)->whereIn('status', ['Reserved', 'Consumed'])->sum('quantity') >= $promotion->quota_per_outlet) {
            throw ValidationException::withMessages(['promotionCode' => 'Kuota promosi untuk outlet ini telah habis.']);
        }
        if ($promotion->budget_amount !== null && $promotion->used_budget >= $promotion->budget_amount) {
            throw ValidationException::withMessages(['promotionCode' => 'Anggaran promosi telah habis.']);
        }
    }

    public function reserve(Promotion $promotion, OutletTransaction $transaction, float $benefitAmount): PromotionUsage
    {
        return DB::transaction(function () use ($promotion, $transaction, $benefitAmount): PromotionUsage {
            $locked = Promotion::lockForUpdate()->findOrFail($promotion->id);
            $this->assertEligible($locked, $transaction->outlet, $transaction->sales, $transaction->amount + $benefitAmount);
            $usage = PromotionUsage::firstOrCreate(
                ['reference' => 'order-'.$transaction->id],
                ['promotion_id' => $locked->id, 'branch_id' => $transaction->branch_id, 'outlet_id' => $transaction->outlet_id, 'sales_id' => $transaction->sales_id, 'outlet_transaction_id' => $transaction->id, 'quantity' => 1, 'benefit_amount' => $benefitAmount, 'status' => 'Reserved', 'reserved_at' => now()],
            );
            if ($usage->wasRecentlyCreated) {
                $locked->increment('used_quota');
                if ($benefitAmount > 0) {
                    $locked->increment('used_budget', $benefitAmount);
                }
            }

            return $usage;
        });
    }

    public function consumeFor(OutletTransaction $transaction): void
    {
        PromotionUsage::where('outlet_transaction_id', $transaction->id)->where('status', 'Reserved')->update(['status' => 'Consumed', 'consumed_at' => now()]);
    }

    public function releaseFor(OutletTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction): void {
            PromotionUsage::where('outlet_transaction_id', $transaction->id)->where('status', 'Reserved')->lockForUpdate()->get()->each(function (PromotionUsage $usage): void {
                $promotion = Promotion::lockForUpdate()->find($usage->promotion_id);
                if ($promotion) {
                    $promotion->decrement('used_quota', $usage->quantity);
                    if ((float) $usage->benefit_amount > 0) {
                        $promotion->decrement('used_budget', (float) $usage->benefit_amount);
                    }
                }
                $usage->update(['status' => 'Released', 'released_at' => now()]);
            });
        });
    }
}
