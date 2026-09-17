<?php

namespace App\Services;

use App\Models\Unit;
use App\Repositories\UnitRepository;
use Illuminate\Validation\ValidationException;

class UnitService
{
    public function __construct(
        protected UnitRepository $unitRepository,
    ) {}

    public function list(array $filters)
    {
        return $this->unitRepository->paginate($filters);
    }

    public function options()
    {
        return $this->unitRepository->all();
    }

    public function find(int $id): Unit
    {
        return $this->unitRepository->findById($id);
    }

    public function create(array $data): Unit
    {
        return $this->unitRepository->create($data);
    }

    public function update(Unit $unit, array $data): Unit
    {
        return $this->unitRepository->update($unit, $data);
    }

    /**
     * isUsedByProduct() kích hoạt thật — Product module đã hoàn thành
     * (Phase 1), cùng lý do CategoryService::delete(): tránh FK RESTRICT
     * throw QueryException thô 500.
     */
    public function delete(Unit $unit): void
    {
        if ($this->unitRepository->isUsedByProduct($unit)) {
            throw ValidationException::withMessages([
                'unit' => 'Không thể xoá đơn vị tính đang được sản phẩm sử dụng.',
            ]);
        }

        $this->unitRepository->delete($unit);
    }
}