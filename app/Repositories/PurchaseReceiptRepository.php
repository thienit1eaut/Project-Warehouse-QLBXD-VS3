<?php

namespace App\Repositories;

use App\Models\PurchaseReceipt;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Persistence/query only. KHÔNG chứa business rule inventory.
 */
class PurchaseReceiptRepository
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return PurchaseReceipt::query()
            ->with(['supplier:id,code,name', 'warehouse:id,code,name'])
            ->withCount('items')
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where('receipt_code', 'like', "%{$search}%")
            )
            ->when(
                $filters['status'] ?? null,
                fn ($q, $status) => $q->where('status', $status)
            )
            ->when(
                $filters['supplier_id'] ?? null,
                fn ($q, $supplierId) => $q->where('supplier_id', $supplierId)
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): PurchaseReceipt
    {
        return PurchaseReceipt::with([
            'supplier:id,code,name',
            'warehouse:id,code,name',
            'creator:id,name',
            'items.product:id,sku,name',
        ])->findOrFail($id);
    }

    /** Phải gọi bên trong DB::transaction() ở Service. */
    public function findForUpdate(int $id): PurchaseReceipt
    {
        return PurchaseReceipt::query()->lockForUpdate()->findOrFail($id);
    }

    public function create(array $data): PurchaseReceipt
    {
        return PurchaseReceipt::create($data);
    }

    public function update(PurchaseReceipt $receipt, array $data): PurchaseReceipt
    {
        $receipt->update($data);

        return $receipt->fresh();
    }
}