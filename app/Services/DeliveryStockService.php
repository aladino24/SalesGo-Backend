<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class DeliveryStockService
{
    /** @param array<int, array<string, mixed>> $items */
    public function normalizeItems(int $branchId, array $items): array
    {
        $normalized = [];
        foreach ($items as $index => $item) {
            $productId = $item['productId'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);
            if (! $productId || $quantity < 1) {
                throw ValidationException::withMessages(["items.$index" => 'Produk dan kuantitas wajib valid.']);
            }
            $product = Product::where('branch_id', $branchId)->whereKey($productId)->where('is_active', true)->first();
            if (! $product) {
                throw ValidationException::withMessages(["items.$index.productId" => 'Produk tidak tersedia pada cabang ini.']);
            }
            $normalized[] = ['productId' => (string) $product->id, 'productName' => $product->name, 'quantity' => $quantity];
        }

        return $normalized;
    }

    /** @param array<int, array<string, mixed>> $items */
    public function reserve(int $branchId, array $items): void
    {
        $this->moveReservation($branchId, $items, 'reserve');
    }

    /** @param array<int, array<string, mixed>> $items */
    public function release(int $branchId, array $items): void
    {
        $this->moveReservation($branchId, $items, 'release');
    }

    /** @param array<int, array<string, mixed>> $items */
    public function consume(int $branchId, array $items): void
    {
        foreach ($items as $item) {
            $product = $this->lockedProduct($branchId, $item);
            $quantity = (int) $item['quantity'];
            if ($product->reserved_stock < $quantity || $product->stock < $quantity) {
                throw ValidationException::withMessages(['items' => "Reservasi stok {$product->name} tidak valid."]);
            }
            $product->decrement('stock', $quantity);
            $product->decrement('reserved_stock', $quantity);
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    private function moveReservation(int $branchId, array $items, string $operation): void
    {
        foreach ($items as $item) {
            $product = $this->lockedProduct($branchId, $item);
            $quantity = (int) $item['quantity'];
            if ($operation === 'reserve') {
                if (($product->stock - $product->reserved_stock) < $quantity) {
                    throw ValidationException::withMessages(['items' => "Stok tersedia {$product->name} tidak mencukupi untuk surat jalan."]);
                }
                $product->increment('reserved_stock', $quantity);
            } else {
                if ($product->reserved_stock < $quantity) {
                    throw ValidationException::withMessages(['items' => "Reservasi stok {$product->name} tidak valid."]);
                }
                $product->decrement('reserved_stock', $quantity);
            }
        }
    }

    /** @param array<string, mixed> $item */
    private function lockedProduct(int $branchId, array $item): Product
    {
        $product = Product::where('branch_id', $branchId)->whereKey($item['productId'] ?? null)->lockForUpdate()->first();
        if (! $product) {
            throw ValidationException::withMessages(['items' => 'Produk surat jalan tidak tersedia.']);
        }

        return $product;
    }
}
