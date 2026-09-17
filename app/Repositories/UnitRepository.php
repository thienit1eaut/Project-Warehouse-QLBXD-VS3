<?php

namespace App\Repositories;

use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UnitRepository
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return Unit::query()
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where(function ($sub) use ($search) {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
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
        return Unit::query()->orderBy('name')->get(['id', 'code', 'name']);
    }

    public function findById(int $id): Unit
    {
        return Unit::findOrFail($id);
    }

    public function create(array $data): Unit
    {
        return Unit::create($data);
    }

    public function update(Unit $unit, array $data): Unit
    {
        $unit->update($data);

        return $unit->fresh();
    }

    public function delete(Unit $unit): bool
    {
        return (bool) $unit->delete();
    }

    /**
     * Kích hoạt thật — Product module đã hoàn thành (Phase 1).
     */
    public function isUsedByProduct(Unit $unit): bool
    {
        return $unit->products()->exists();
    }
}