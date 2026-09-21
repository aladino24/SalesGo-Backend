<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductUom;
use App\Models\Promotion;
use Illuminate\Validation\ValidationException;

class SalesOrderCalculator
{
    public function normalize(int $branchId, array $items, ?Promotion $promotion = null, bool $lock = false, array $salesDivisionIds = []): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Order harus memiliki minimal satu produk.']);
        }
        $normalized = [];
        $grossTotal = 0.0;
        foreach ($items as $index => $item) {
            $productId = $item['productId'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);
            if (! $productId || $quantity < 1) {
                throw ValidationException::withMessages(["items.$index" => 'Produk dan kuantitas wajib valid.']);
            }
            $query = Product::where('branch_id', $branchId)->whereKey($productId)->where('is_active', true);
            if ($salesDivisionIds !== []) {
                $query->whereIn('sales_division_id', $salesDivisionIds);
            }
            if ($lock) {
                $query->lockForUpdate();
            }
            $product = $query->first();
            if (! $product) {
                throw ValidationException::withMessages(["items.$index.productId" => 'Produk tidak tersedia untuk divisi sales ini.']);
            }
            $uom = $this->resolveUom($product, $item['productUomId'] ?? null);
            if ($quantity < $uom->minimum_quantity || ($uom->maximum_quantity !== null && $quantity > $uom->maximum_quantity)) {
                throw ValidationException::withMessages(["items.$index.quantity" => "Jumlah {$uom->code} tidak memenuhi batas produk."]);
            }
            $baseQuantity = $quantity * $uom->conversion_to_base;
            $gross = (float) $uom->price * $quantity;
            $normalized[] = [
                'productId' => (string) $product->id,
                'productName' => $product->name,
                'productUomId' => (string) $uom->id,
                'uomCode' => $uom->code,
                'uomName' => $uom->name,
                'conversionToBase' => $uom->conversion_to_base,
                'baseQuantity' => $baseQuantity,
                'quantity' => $quantity,
                'unitPrice' => (float) $uom->price,
                'gross' => $gross,
            ];
            $grossTotal += $gross;
        }

        $promotionApplies = $promotion && $grossTotal >= (float) $promotion->minimum_order_amount;
        $remainingFixedDiscount = $promotionApplies && $promotion->type === 'Fixed' ? (float) $promotion->value : 0.0;
        $remainingMaximumDiscount = $promotionApplies && $promotion->maximum_discount !== null ? (float) $promotion->maximum_discount : null;
        $total = 0.0;
        foreach ($normalized as &$item) {
            $discount = 0.0;
            $eligible = $promotionApplies && (! $promotion->product_id || (int) $promotion->product_id === (int) $item['productId']);
            if ($eligible && $promotion->type === 'Percentage') {
                $discount = $item['gross'] * ((float) $promotion->value / 100);
            } elseif ($eligible && $promotion->type === 'Fixed') {
                $discount = min((float) $item['gross'], $remainingFixedDiscount);
                $remainingFixedDiscount -= $discount;
            }
            if ($remainingMaximumDiscount !== null) {
                $discount = min($discount, $remainingMaximumDiscount);
                $remainingMaximumDiscount -= $discount;
            }
            $item['discount'] = round($discount, 2);
            $item['subtotal'] = max(0, $item['gross'] - $item['discount']);
            unset($item['gross']);
            $total += $item['subtotal'];
        }
        unset($item);

        return ['items' => $normalized, 'total' => round($total, 2)];
    }

    public function commitStock(int $branchId, array $items): void
    {
        $this->adjustStock($branchId, $items, -1);
    }

    /**
     * Canonicalize non-sales movement items from the branch product master.
     * The mobile client is never trusted for product name or unit price.
     */
    public function normalizeMovementItems(int $branchId, array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Transaksi harus memiliki minimal satu produk.']);
        }

        $normalized = [];
        $total = 0.0;
        foreach ($items as $index => $item) {
            $productId = $item['productId'] ?? null;
            $quantity = (int) ($item['baseQuantity'] ?? $item['quantity'] ?? 0);
            if (! $productId || $quantity < 1) {
                throw ValidationException::withMessages(["items.$index" => 'Produk dan kuantitas wajib valid.']);
            }

            $product = Product::where('branch_id', $branchId)->whereKey($productId)->first();
            if (! $product) {
                throw ValidationException::withMessages(["items.$index.productId" => 'Produk tidak tersedia pada cabang ini.']);
            }

            $subtotal = (float) $product->price * $quantity;
            $normalized[] = [
                'productId' => (string) $product->id,
                'productName' => $product->name,
                'quantity' => $quantity,
                'unitPrice' => (float) $product->price,
                'subtotal' => $subtotal,
            ];
            $total += $subtotal;
        }

        return ['items' => $normalized, 'total' => round($total, 2)];
    }

    /** @param array<int, array<string, mixed>> $items */
    public function adjustStock(int $branchId, array $items, int $direction): void
    {
        foreach ($items as $index => $item) {
            $productId = $item['productId'] ?? null;
            $quantity = (int) ($item['baseQuantity'] ?? $item['quantity'] ?? 0);
            if (! $productId || $quantity < 1) {
                throw ValidationException::withMessages(["items.$index" => 'Produk dan kuantitas wajib valid.']);
            }

            $product = Product::where('branch_id', $branchId)->whereKey($productId)->lockForUpdate()->first();
            if (! $product) {
                throw ValidationException::withMessages(["items.$index.productId" => 'Produk tidak tersedia pada cabang ini.']);
            }
            if ($direction < 0 && ($product->stock - $product->reserved_stock) < $quantity) {
                throw ValidationException::withMessages(['items' => "Stok tersedia {$product->name} tidak mencukupi."]);
            }

            $product->increment('stock', $direction * $quantity);
        }
    }

    private function resolveUom(Product $product, mixed $uomId): ProductUom
    {
        $query = $product->uoms()->where('is_active', true);
        $uom = $uomId ? $query->whereKey($uomId)->first() : $query->where('is_default', true)->first();

        if (! $uom) {
            throw ValidationException::withMessages(['items' => "Satuan jual {$product->name} tidak tersedia."]);
        }

        return $uom;
    }
}
