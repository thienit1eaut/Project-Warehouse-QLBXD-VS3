<?php

namespace App\Services;

use App\Models\Stock;
use App\Repositories\StockMovementRepository;
use App\Repositories\StockRepository;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * CHỈ ĐỌC. Không mutate stocks.quantity_on_hand - việc đó thuộc về InventoryService.
 */
class StockService
{
    public function __construct(
        protected StockRepository $repository,
        protected StockMovementRepository $movementRepository
    ) {
    }

    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginate($filters);
    }

    public function find(int $id): ?Stock
    {
        return $this->repository->find($id);
    }

    public function movementHistory(Stock $stock, int $perPage = 20): LengthAwarePaginator
    {
        return $this->movementRepository->getByStock($stock->warehouse_id, $stock->product_id, $perPage);
    }
}
