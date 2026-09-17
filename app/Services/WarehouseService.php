<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Repositories\WarehouseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class WarehouseService
{
    public function __construct(protected WarehouseRepository $repository)
    {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function find(int $id): ?Warehouse
    {
        return $this->repository->find($id);
    }

    public function create(array $data): Warehouse
    {
        return $this->repository->create($data);
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        return $this->repository->update($warehouse, $data);
    }

    public function options(): Collection
    {
        return $this->repository->options();
    }

    /**
     * Chặn xoá nếu kho đã có lịch sử tồn kho/giao dịch.
     * Gợi ý người dùng chuyển is_active = false thay vì xoá.
     */
    public function delete(Warehouse $warehouse): void
    {
        if ($this->repository->hasStockOrMovement($warehouse)) {
            throw ValidationException::withMessages([
                'warehouse' => 'Không thể xoá kho đã có lịch sử tồn kho/giao dịch. Vui lòng chuyển sang "Ngừng hoạt động" thay vì xoá.',
            ]);
        }

        $this->repository->delete($warehouse);
    }
}
