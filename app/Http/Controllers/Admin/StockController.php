<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Services\BrandService;
use App\Services\CategoryService;
use App\Services\InventoryMonitoringService;
use App\Services\StockService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only: KHÔNG có store/update/destroy. Mọi thay đổi tồn kho đi qua InventoryService.
 * index() là Inventory Overview (Phase N): Product × Warehouse + trạng thái tính runtime.
 */
class StockController extends Controller
{
    public function __construct(
        protected StockService $stockService,
        protected WarehouseService $warehouseService,
        protected CategoryService $categoryService,
        protected BrandService $brandService,
        protected InventoryMonitoringService $monitoring,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['warehouse_id', 'category_id', 'brand_id', 'status', 'search']);

        return Inertia::render('Admin/Stock/Index', [
            'pageTitle' => 'Tồn kho',
            'rows' => $this->monitoring->overview($filters),
            'filters' => $filters,
            'warehouseOptions' => $this->warehouseService->options(),
            'categoryOptions' => $this->categoryService->options(),
            'brandOptions' => $this->brandService->options(),
        ]);
    }

    public function show(Stock $stock): Response
    {
        $stock = $this->stockService->find($stock->id);
        $movements = $this->stockService->movementHistory($stock);

        return Inertia::render('Admin/Stock/Show', [
            'pageTitle' => 'Chi tiết tồn kho: ' . $stock->product->name,
            'stock' => $stock,
            'movements' => $movements,
        ]);
    }
}