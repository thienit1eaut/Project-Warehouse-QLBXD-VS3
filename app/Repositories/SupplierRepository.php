<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class SupplierRepository
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Supplier::query()
            ->when(
                class_exists(Product::class),
                fn ($q) => $q->withCount('products')
            )
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where(function ($sub) use ($search) {
                    // search theo code, name, phone, email — đúng yêu cầu nghiệp vụ
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
            )
            ->when(
                isset($filters['is_active']) && $filters['is_active'] !== '',
                fn ($q) => $q->where('is_active', (bool) $filters['is_active'])
            )
            ->orderBy($filters['sort'] ?? 'name', $filters['direction'] ?? 'asc')
            ->paginate($perPage)
            ->withQueryString();
    }
 
    public function all(): Collection
    {
        return Supplier::query()->orderBy('name')->get(['id', 'code', 'name']);
    }
 
    public function findById(int $id): Supplier
    {
        return Supplier::findOrFail($id);
    }
 
    public function create(array $data): Supplier
    {
        return Supplier::create($data);
    }
 
    public function update(Supplier $supplier, array $data): Supplier
    {
        $supplier->update($data);
 
        return $supplier->fresh();
    }
 
    public function delete(Supplier $supplier): bool
    {
        return (bool) $supplier->delete();
    }
 
    public function isUsedByProduct(Supplier $supplier): bool
    {
        if (! class_exists(Product::class)) {
            return false;
        }
 
        return $supplier->products()->exists();
    }
 
    // public function isUsedByPurchaseOrder(Supplier $supplier): bool
    // {
    //     if (! class_exists(PurchaseOrder::class)) {
    //         return false;
    //     }
 
    //     return $supplier->purchaseOrders()->exists();
    // }
}
