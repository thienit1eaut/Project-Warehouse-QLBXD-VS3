<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Services\StockService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only: KHÔNG có store/update/destroy. Mọi thay đổi tồn kho đi qua InventoryService
 * (được gọi từ các module khác như Purchase Order / Sales Order - ngoài phạm vi Phase I).
 */
class StockController extends Controller
{
    public function __construct(
        protected StockService $stockService,
        protected WarehouseService $warehouseService
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['warehouse_id', 'search']);

        return Inertia::render('Admin/Stock/Index', [
            'stocks' => $this->stockService->list($filters),
            'filters' => $filters,
            'warehouseOptions' => $this->warehouseService->options(),
        ]);
    }

    public function show(Stock $stock): Response
    {
        $stock = $this->stockService->find($stock->id);
        $movements = $this->stockService->movementHistory($stock);

        return Inertia::render('Admin/Stock/Show', [
            'stock' => $stock,
            'movements' => $movements,
        ]);
    }
}
