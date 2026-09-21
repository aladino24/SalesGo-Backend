<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Outlet;
use App\Models\OutletTarget;
use App\Models\OutletTransaction;
use App\Models\Product;
use App\Models\Promotion;
use App\Services\MasterRevisionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MasterController extends ApiController
{
    public function products(Request $request)
    {
        $user = $request->user();
        $divisionIds = $user->divisions()->pluck('sales_divisions.id');
        if ($divisionIds->isEmpty() && $user->sales_division_id) {
            $divisionIds->push($user->sales_division_id);
        }
        $products = Product::query()
            ->with(['division:id,code,name', 'uoms' => fn ($query) => $query->where('is_active', true)])
            ->where('branch_id', $this->branchId($user))
            ->where('is_active', true)
            ->whereNotNull('sales_division_id')
            ->when($user->role->slug === 'sales', fn ($query) => $query->whereIn('sales_division_id', $divisionIds))
            ->orderBy('brand')
            ->orderBy('name')
            ->get();

        return response()->json($products->map(fn (Product $product) => $this->productPayload($product))->values());
    }

    public function productImage(Request $request, Product $product)
    {
        abort_unless($product->branch_id === $this->branchId($request->user()), 403);
        $allowedDivisionIds = $request->user()->divisions()->pluck('sales_divisions.id');
        if ($allowedDivisionIds->isEmpty() && $request->user()->sales_division_id) {
            $allowedDivisionIds->push($request->user()->sales_division_id);
        }
        abort_unless($request->user()->role->slug !== 'sales' || $allowedDivisionIds->contains($product->sales_division_id), 403);
        abort_unless($product->image_path && Storage::disk('local')->exists($product->image_path), 404);

        return Storage::disk('local')->response($product->image_path);
    }

    public function outlets(Request $request)
    {
        return response()->json(Outlet::with(['divisions:id,code,name', 'routeAssignments.sales:id,name,employee_code,role_id'])
            ->where('branch_id', $this->branchId($request->user()))
            ->where('status', 'Active')
            ->get()
            ->map(fn ($o) => $this->outlet($o))
            ->values());
    }

    public function snapshot(Request $request, MasterRevisionService $revision)
    {
        $meta = $revision->forBranch($this->branchId($request->user()));

        return response()->json([...$meta, 'datasets' => ['products' => $this->products($request)->getData(true), 'outlets' => $this->outlets($request)->getData(true), 'promotions' => $this->promotions($request)->getData(true), 'orderPolicy' => $this->orderPolicy()]]);
    }

    public function promotions(Request $request)
    {
        return response()->json(Promotion::where('branch_id', $this->branchId($request->user()))
            ->where('ends_at', '>=', now())
            ->get()
            ->map(fn (Promotion $promotion) => $this->promotionPayload($promotion))
            ->values());
    }

    public function promotion(Request $request, Promotion $promotion)
    {
        abort_unless($promotion->branch_id === $this->branchId($request->user()), 403);

        return response()->json($this->promotionPayload($promotion));
    }

    public function performance(Request $request, Outlet $outlet)
    {
        abort_unless($outlet->branch_id === $this->branchId($request->user()), 403);

        $period = $this->period($request->query('period'));
        $transactions = OutletTransaction::where('branch_id', $outlet->branch_id)
            ->where('outlet_id', $outlet->id)
            ->where('type', 'sales_order')
            ->whereIn('status', ['Committed', 'Completed'])
            ->whereBetween('occurred_at', [$period, $period->endOfMonth()])
            ->get();
        $achievement = (float) $transactions->sum('amount');
        $target = (float) (OutletTarget::where('branch_id', $outlet->branch_id)
            ->where('outlet_id', $outlet->id)
            ->whereDate('period', $period)
            ->value('revenue_target') ?? 0);
        $sold = [];
        foreach ($transactions as $transaction) {
            foreach ($transaction->items ?? [] as $item) {
                $productId = (string) ($item['productId'] ?? '');
                if ($productId === '') {
                    continue;
                }
                $quantity = (int) ($item['quantity'] ?? 0);
                $subtotal = (float) ($item['subtotal'] ?? ((float) ($item['unitPrice'] ?? 0) * $quantity - (float) ($item['discount'] ?? 0)));
                $sold[$productId] = [
                    'id' => $productId,
                    'name' => (string) ($item['productName'] ?? 'Produk'),
                    'quantity' => ($sold[$productId]['quantity'] ?? 0) + $quantity,
                    'revenue' => ($sold[$productId]['revenue'] ?? 0) + $subtotal,
                ];
            }
        }
        $products = Product::where('branch_id', $outlet->branch_id)->where('is_active', true)->get();
        $topProducts = collect($sold)->sortByDesc('revenue')->take(5)->values()->all();
        $unsoldProducts = $products->filter(fn ($product) => ! isset($sold[(string) $product->id]))
            ->map(fn ($product) => ['id' => (string) $product->id, 'name' => $product->name, 'stock' => $product->stock])->values();
        $potentialProducts = $unsoldProducts->filter(fn ($product) => $product['stock'] > 0)->sortByDesc('stock')->take(5)->values();

        return response()->json(['period' => $period->format('Y-m'), 'target' => $target, 'achievement' => $achievement, 'achievementPercent' => $target <= 0 ? 0 : round($achievement / $target * 100, 2), 'topProducts' => $topProducts, 'unsoldProducts' => $unsoldProducts, 'potentialProducts' => $potentialProducts]);
    }

    private function period(?string $value): CarbonImmutable
    {
        return $value ? CarbonImmutable::createFromFormat('Y-m', $value, 'UTC')->startOfMonth() : CarbonImmutable::now('UTC')->startOfMonth();
    }

    private function outlet(Outlet $o): array
    {
        return ['id' => (string) $o->id, 'branchId' => (string) $o->branch_id, 'branchCode' => $o->branch_code, 'code' => $o->code, 'name' => $o->name, 'address' => $o->address, 'type' => $o->type, 'ownerName' => $o->owner_name, 'contactName' => $o->contact_name, 'phone' => $o->phone, 'latitude' => $o->latitude, 'longitude' => $o->longitude, 'salesResponsible' => $o->sales_responsible_id ? 'Assigned Sales' : null, 'divisions' => $o->divisions->map(fn ($division) => ['code' => $division->code, 'name' => $division->name])->values(), 'salesSchedules' => $o->routeAssignments->where('is_active', true)->map(fn ($assignment) => ['salesId' => (string) $assignment->sales_id, 'name' => $assignment->sales?->name ?? '-', 'employeeCode' => $assignment->sales?->employee_code ?? '', 'dayOfWeek' => $assignment->day_of_week, 'weekOfMonth' => $assignment->week_of_month])->values(), 'status' => $o->status];
    }

    private function promotionPayload(Promotion $promotion): array
    {
        $status = ! $promotion->is_active ? 'Nonaktif' : ($promotion->starts_at->isFuture() ? 'Akan Datang' : 'Aktif');

        $status = $promotion->status !== 'Active' ? $promotion->status : $status;
        return ['id' => (string) $promotion->id, 'code' => $promotion->code, 'title' => $promotion->name, 'description' => $promotion->description ?? '', 'startAt' => $promotion->starts_at->toIso8601String(), 'endAt' => $promotion->ends_at->toIso8601String(), 'status' => $status, 'imageUrl' => $promotion->image_url ?? '', 'type' => $promotion->type, 'programType' => $promotion->program_type, 'value' => (float) $promotion->value, 'maximumDiscount' => $promotion->maximum_discount === null ? null : (float) $promotion->maximum_discount, 'minimumOrderAmount' => (float) $promotion->minimum_order_amount, 'productId' => $promotion->product_id ? (string) $promotion->product_id : null, 'eligibilityRules' => $promotion->eligibility_rules ?? [], 'benefitRules' => $promotion->benefit_rules ?? [], 'stackingRules' => $promotion->stacking_rules ?? [], 'quotaTotal' => $promotion->quota_total, 'quotaPerOutlet' => $promotion->quota_per_outlet, 'usedQuota' => $promotion->used_quota, 'budgetAmount' => $promotion->budget_amount === null ? null : (float) $promotion->budget_amount, 'usedBudget' => (float) $promotion->used_budget, 'terms' => $promotion->terms];
    }

    private function productPayload(Product $product): array
    {
        return [
            'id' => (string) $product->id,
            'branchId' => (string) $product->branch_id,
            'branchCode' => $product->branch_code,
            'divisionCode' => $product->division?->code,
            'divisionName' => $product->division?->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'name' => $product->name,
            'brand' => $product->brand,
            'variant' => $product->variant,
            'size' => $product->size,
            'uom' => $product->uom,
            'unitsPerCase' => $product->units_per_case,
            'uoms' => $product->uoms->map(fn ($uom) => [
                'id' => (string) $uom->id,
                'code' => $uom->code,
                'name' => $uom->name,
                'conversionToBase' => $uom->conversion_to_base,
                'price' => (float) $uom->price,
                'minimumQuantity' => $uom->minimum_quantity,
                'maximumQuantity' => $uom->maximum_quantity,
                'isDefault' => $uom->is_default,
            ])->values(),
            'category' => $product->category,
            'price' => (float) $product->price,
            'stock' => $product->stock,
            'imageUrl' => $product->image_path ? '/api/v1/master/products/'.$product->id.'/image' : $product->image_url,
        ];
    }

    private function orderPolicy(): array
    {
        return [
            'minimumUnits' => max(1, (int) config('salesgo.order.minimum_units', 1)),
            'maximumUnits' => max(1, (int) config('salesgo.order.maximum_units', 1000)),
            'minimumAmount' => max(0, (float) config('salesgo.order.minimum_amount', 0)),
            'maximumAmount' => max(0, (float) config('salesgo.order.maximum_amount', 0)),
        ];
    }
}
