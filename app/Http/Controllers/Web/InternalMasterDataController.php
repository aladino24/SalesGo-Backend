<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Outlet;
use App\Models\OutletRouteAssignment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\SalesDivision;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InternalMasterDataController extends Controller
{
    public function index(Request $request): View
    {
        $actor = $this->actor($request);
        $branchId = $this->branchId($request, $actor);
        $branches = $actor->role?->slug === 'it'
            ? Branch::orderBy('code')->get()
            : Branch::whereKey($branchId)->get();
        $branchRows = $branches->map(fn (Branch $item) => [
            'id' => $item->id,
            'code' => $item->code,
            'name' => $item->name,
            'active' => $item->is_active,
            'radius' => $item->default_geofence_radius_meters,
        ])->values()->all();

        return view('internal-master-data.index', [
            'actor' => $actor,
            'branch' => Branch::findOrFail($branchId),
            'branches' => $branches,
            'branchRows' => $branchRows,
            'roles' => Role::orderBy('code')->get(),
            'divisions' => SalesDivision::orderBy('code')->get(),
            'users' => User::with(['role', 'division'])->where('branch_id', $branchId)->orderBy('employee_code')->get(),
            'outlets' => Outlet::with('divisions')->where('branch_id', $branchId)->orderBy('code')->get(),
            'products' => Product::with('division')->where('branch_id', $branchId)->orderBy('sku')->get(),
            'promotions' => Promotion::where('branch_id', $branchId)->latest('updated_at')->get(),
            'routes' => OutletRouteAssignment::with(['outlet', 'sales'])->where('branch_id', $branchId)->orderBy('day_of_week')->orderBy('week_of_month')->get(),
        ]);
    }

    public function store(Request $request, string $entity, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        $branchId = $this->branchId($request, $actor);
        $model = match ($entity) {
            'branch' => $this->createBranch($request, $actor),
            'division' => $this->createDivision($request),
            'user' => $this->createUser($request, $branchId, $actor),
            'outlet' => $this->createOutlet($request, $branchId),
            'product' => $this->createProduct($request, $branchId),
            'promotion' => $this->createPromotion($request, $branchId),
            'route' => $this->createRoute($request, $branchId),
            default => abort(404),
        };
        $audit->record($actor->id, $branchId, 'master_created_web', $model::class, $model->id, ['entity' => $entity]);

        return back()->with('success', ucfirst($entity).' berhasil ditambahkan.');
    }

    public function show(Request $request, string $entity, int $id): JsonResponse
    {
        $actor = $this->actor($request);
        $branchId = $this->branchId($request, $actor);

        $model = match ($entity) {
            'user' => User::with(['role', 'division', 'divisions'])->where('branch_id', $branchId)->findOrFail($id),
            'outlet' => Outlet::with('divisions')->where('branch_id', $branchId)->findOrFail($id),
            'product' => Product::with('division')->where('branch_id', $branchId)->findOrFail($id),
            'promotion' => Promotion::where('branch_id', $branchId)->findOrFail($id),
            'route' => OutletRouteAssignment::with(['outlet', 'sales'])->where('branch_id', $branchId)->findOrFail($id),
            'division' => SalesDivision::findOrFail($id),
            'branch' => Branch::when($actor->role?->slug !== 'it', fn ($query) => $query->whereKey($branchId))->findOrFail($id),
            default => abort(404),
        };

        $data = match ($entity) {
            'user' => [
                'Kode karyawan' => $model->employee_code,
                'Nama' => $model->name,
                'Username' => $model->username,
                'Email' => $model->email,
                'Role' => $model->role?->name,
                'Divisi utama' => $model->division?->name,
                'Seluruh divisi' => $model->divisions->pluck('name')->join(', '),
                'Maksimum perangkat' => $model->max_devices,
                'Status' => $model->is_active ? 'Aktif' : 'Nonaktif',
                'Dibuat' => $model->created_at?->format('d M Y H:i'),
            ],
            'outlet' => [
                'Kode outlet' => $model->code,
                'Nama' => $model->name,
                'Tipe' => $model->type,
                'Pemilik' => $model->owner_name,
                'Kontak' => $model->contact_name,
                'Telepon' => $model->phone,
                'Alamat' => $model->address,
                'Latitude' => $model->latitude,
                'Longitude' => $model->longitude,
                'Divisi layanan' => $model->divisions->pluck('name')->join(', '),
                'Status' => $model->status,
                'Dibuat' => $model->created_at?->format('d M Y H:i'),
            ],
            'product' => [
                'SKU' => $model->sku,
                'Nama produk' => $model->name,
                'Divisi' => $model->division?->name,
                'Merek' => $model->brand,
                'Varian' => $model->variant,
                'Ukuran' => $model->size,
                'UOM' => $model->uom,
                'Kategori' => $model->category,
                'Barcode' => $model->barcode,
                'Harga' => number_format((float) $model->price, 0, ',', '.'),
                'Stok' => $model->stock,
                'Status' => $model->is_active ? 'Aktif' : 'Nonaktif',
            ],
            'promotion' => [
                'Kode' => $model->code,
                'Nama' => $model->name,
                'Keterangan' => $model->description,
                'Tipe' => $model->type,
                'Nilai' => $model->value,
                'Minimum order' => $model->minimum_order_amount,
                'Maksimum diskon' => $model->maximum_discount,
                'Kuota total' => $model->quota_total,
                'Kuota terpakai' => $model->used_quota,
                'Budget' => $model->budget_amount,
                'Budget terpakai' => $model->used_budget,
                'Periode mulai' => $model->starts_at?->format('d M Y H:i'),
                'Periode selesai' => $model->ends_at?->format('d M Y H:i'),
                'Status' => $model->status,
            ],
            'route' => [
                'Outlet' => $model->outlet?->name,
                'Kode outlet' => $model->outlet?->code,
                'Sales' => $model->sales?->name,
                'Kode sales' => $model->sales?->employee_code,
                'Hari' => $model->day_of_week,
                'Minggu' => $model->week_of_month,
                'Status' => $model->is_active ? 'Aktif' : 'Nonaktif',
            ],
            'division' => ['Kode' => $model->code, 'Nama divisi' => $model->name],
            'branch' => [
                'Kode cabang' => $model->code,
                'Nama cabang' => $model->name,
                'Radius geofence default' => $model->default_geofence_radius_meters.' m',
                'Status' => $model->is_active ? 'Aktif' : 'Nonaktif',
            ],
        };

        $imageUrl = $entity === 'product'
            ? ($model->image_path ? route('internal.master-data.product-image', $model->id) : $model->image_url)
            : null;

        return response()->json(['title' => 'Detail '.ucfirst($entity), 'data' => $data, 'imageUrl' => $imageUrl]);
    }

    public function productImage(Request $request, int $id)
    {
        $actor = $this->actor($request);
        $branchId = $this->branchId($request, $actor);
        $product = Product::where('branch_id', $branchId)->findOrFail($id);

        abort_unless($product->image_path && Storage::disk('local')->exists($product->image_path), 404);

        return Storage::disk('local')->response($product->image_path);
    }

    public function toggle(Request $request, string $entity, int $id, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request);
        $branchId = $this->branchId($request, $actor);
        $model = match ($entity) {
            'user' => User::where('branch_id', $branchId)->findOrFail($id),
            'outlet' => Outlet::where('branch_id', $branchId)->findOrFail($id),
            'product' => Product::where('branch_id', $branchId)->findOrFail($id),
            'promotion' => Promotion::where('branch_id', $branchId)->findOrFail($id),
            'route' => OutletRouteAssignment::where('branch_id', $branchId)->findOrFail($id),
            default => abort(404),
        };
        $active = $request->boolean('active');
        match ($entity) {
            'user' => $model->update(['is_active' => $active]),
            'outlet' => $model->update(['status' => $active ? 'Active' : 'Inactive']),
            'product' => $model->update(['is_active' => $active]),
            'promotion' => $model->update(['is_active' => $active, 'status' => $active ? 'Active' : 'Paused']),
            'route' => $model->update(['is_active' => $active]),
        };
        $audit->record($actor->id, $branchId, 'master_status_updated_web', $model::class, $model->id, ['entity' => $entity, 'active' => $active]);

        return back()->with('success', 'Status data diperbarui.');
    }

    private function createBranch(Request $request, User $actor): Branch
    {
        abort_unless($actor->role?->slug === 'it', 403);

        return Branch::create($request->validate(['code' => ['required', 'string', 'size:3', 'unique:branches,code'], 'name' => ['required', 'string', 'max:100']]));
    }

    private function createDivision(Request $request): SalesDivision
    {
        return SalesDivision::create($request->validate(['code' => ['required', 'string', 'size:2', 'unique:sales_divisions,code'], 'name' => ['required', 'string', 'max:100']]));
    }

    private function createUser(Request $request, int $branchId, User $actor): User
    {
        $allowedSlugs = $actor->role?->slug === 'it' ? Role::pluck('slug')->all() : ['operational', 'sales', 'marketing'];
        $data = $request->validate([
            'role_id' => ['required', Rule::exists('roles', 'id')->whereIn('slug', $allowedSlugs)],
            'sales_division_id' => ['required', 'exists:sales_divisions,id'],
            'name' => ['required', 'string', 'max:150'], 'username' => ['required', 'alpha_dash', 'max:100', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:150', 'unique:users,email'], 'password' => ['required', 'string', 'min:8'],
            'max_devices' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
        $branch = Branch::findOrFail($branchId);
        $role = Role::findOrFail($data['role_id']);
        $division = SalesDivision::findOrFail($data['sales_division_id']);
        $increment = ((int) User::where('branch_id', $branchId)->where('role_id', $role->id)->where('sales_division_id', $division->id)->max('increment_no')) + 1;
        abort_if($increment > 99, 422, 'Increment user untuk kombinasi cabang, role, dan divisi telah penuh.');
        $user = User::create(['branch_id' => $branchId, 'role_id' => $role->id, 'sales_division_id' => $division->id, 'employee_code' => sprintf('%s%s%s%02d', $branch->code, $role->code, $division->code, $increment), 'increment_no' => $increment, 'name' => $data['name'], 'username' => $data['username'], 'email' => $data['email'] ?? null, 'password' => Hash::make($data['password']), 'max_devices' => $data['max_devices'], 'is_active' => true]);
        $user->divisions()->sync([$division->id]);

        return $user;
    }

    private function createOutlet(Request $request, int $branchId): Outlet
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'address' => ['required', 'string', 'max:500'], 'type' => ['required', 'string', 'max:60'], 'owner_name' => ['nullable', 'string', 'max:120'], 'contact_name' => ['nullable', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:50'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'], 'division_ids' => ['nullable', 'array'], 'division_ids.*' => ['exists:sales_divisions,id']]);
        $branch = Branch::findOrFail($branchId);
        $next = ((int) Outlet::where('branch_id', $branchId)->max('id')) + 1;
        $outlet = Outlet::create([...$data, 'branch_id' => $branchId, 'branch_code' => $branch->code, 'code' => $branch->code.'-OTL-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT), 'status' => 'Active']);
        $outlet->divisions()->sync($data['division_ids'] ?? []);

        return $outlet;
    }

    private function createProduct(Request $request, int $branchId): Product
    {
        $data = $request->validate(['sales_division_id' => ['required', 'exists:sales_divisions,id'], 'sku' => ['required', 'string', 'max:100', Rule::unique('products')->where('branch_id', $branchId)], 'name' => ['required', 'string', 'max:150'], 'brand' => ['nullable', 'string', 'max:100'], 'variant' => ['nullable', 'string', 'max:100'], 'size' => ['nullable', 'string', 'max:60'], 'uom' => ['nullable', 'string', 'max:30'], 'category' => ['nullable', 'string', 'max:100'], 'barcode' => ['nullable', 'string', 'max:100'], 'price' => ['required', 'numeric', 'min:0'], 'stock' => ['required', 'integer', 'min:0']]);

        return Product::create([...$data, 'branch_id' => $branchId, 'branch_code' => Branch::findOrFail($branchId)->code, 'is_active' => true]);
    }

    private function createPromotion(Request $request, int $branchId): Promotion
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50'], 'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string'], 'type' => ['required', 'in:Fixed,Percentage'], 'value' => ['required', 'numeric', 'min:0'], 'minimum_order_amount' => ['nullable', 'numeric', 'min:0'], 'maximum_discount' => ['nullable', 'numeric', 'min:0'], 'quota_total' => ['nullable', 'integer', 'min:1'], 'quota_per_outlet' => ['nullable', 'integer', 'min:1'], 'budget_amount' => ['nullable', 'numeric', 'min:0'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after_or_equal:starts_at']]);

        return Promotion::create([...$data, 'branch_id' => $branchId, 'program_type' => 'order_discount', 'status' => 'Active', 'used_quota' => 0, 'used_budget' => 0, 'is_active' => true]);
    }

    private function createRoute(Request $request, int $branchId): OutletRouteAssignment
    {
        $data = $request->validate(['outlet_id' => ['required', 'exists:outlets,id'], 'sales_id' => ['required', 'exists:users,id'], 'day_of_week' => ['required', 'integer', 'between:1,7'], 'week_of_month' => ['required', 'integer', 'between:1,4']]);
        Outlet::whereKey($data['outlet_id'])->where('branch_id', $branchId)->firstOrFail();
        User::whereKey($data['sales_id'])->where('branch_id', $branchId)->firstOrFail();
        abort_if(OutletRouteAssignment::where([...$data, 'branch_id' => $branchId])->exists(), 422, 'Rute yang sama sudah ada.');

        return OutletRouteAssignment::create([...$data, 'branch_id' => $branchId, 'is_active' => true]);
    }

    private function actor(Request $request): User
    {
        $user = $request->attributes->get('internalMasterUser');
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function branchId(Request $request, User $actor): int
    {
        $id = (int) $request->input('branch_id', $actor->branch_id);
        abort_unless($actor->role?->slug === 'it' || $id === $actor->branch_id, 403);

        return $id;
    }
}
