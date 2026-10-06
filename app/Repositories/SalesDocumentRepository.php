<?php

namespace App\Repositories;

use App\Models\SalesDocument;
use App\Models\SalesDocumentItem;
use Illuminate\Pagination\LengthAwarePaginator;

/** Persistence/query only. KHÔNG chứa FIFO hay inventory mutation. */
class SalesDocumentRepository
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $total = SalesDocumentItem::query()
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0)')
            ->whereColumn('sales_document_id', 'sales_documents.id');

        return SalesDocument::query()
            ->select('sales_documents.*')
            ->addSelect(['total_amount' => $total])
            ->with(['customer:id,customer_code,name', 'warehouse:id,code,name'])
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where('document_code', 'like', "%{$search}%")
            )
            ->when(
                $filters['status'] ?? null,
                fn ($q, $status) => $q->where('status', $status)
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): SalesDocument
    {
        return SalesDocument::with([
            'customer:id,customer_code,name',
            'warehouse:id,code,name',
            'creator:id,name',
            'items.product:id,sku,name',
        ])->findOrFail($id);
    }

    /** Phải gọi bên trong DB::transaction() ở Service. */
    public function findForUpdate(int $id): SalesDocument
    {
        return SalesDocument::query()->lockForUpdate()->findOrFail($id);
    }

    public function create(array $data): SalesDocument
    {
        return SalesDocument::create($data);
    }

    public function update(SalesDocument $document, array $data): SalesDocument
    {
        $document->update($data);

        return $document->fresh();
    }

    public function delete(SalesDocument $document): bool
    {
        return (bool) $document->delete();
    }
}