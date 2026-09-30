<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWarehouseRequest;
use App\Http\Requests\Admin\UpdateWarehouseRequest;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WarehouseController extends Controller
{
    public function __construct(
        protected WarehouseService $warehouseService,
        protected StockService $stockService
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'is_active']);

        return Inertia::render('Admin/Warehouses/Index', [
            'pageTitle' => 'Quản lý kho hàng',
            'warehouses' => $this->warehouseService->list($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Warehouses/Form', [
            'pageTitle' => 'Thêm kho hàng',
        ]);
    }

    public function store(StoreWarehouseRequest $request)
    {
        $this->warehouseService->create($request->validated());

        return redirect()
            ->route('admin.warehouses.index')
            ->with('success', 'Tạo kho hàng thành công.');
    }

    /**
     * Hiển thị chi tiết kho + danh sách stock hiện tại trong kho (chỉ đọc, qua StockService).
     */
    public function show(Warehouse $warehouse, Request $request): Response
    {
        $filters = $request->only(['search']);

        $stocks = $this->stockService->list([
            'warehouse_id' => $warehouse->id,
            ...$filters,
        ]);

        return Inertia::render('Admin/Warehouses/Show', [
            'pageTitle' => 'Kho hàng: ' . $warehouse->name,
            'warehouse' => $warehouse,
            'stocks' => $stocks,
            'filters' => $filters,
        ]);
    }

    public function edit(Warehouse $warehouse): Response
    {
        return Inertia::render('Admin/Warehouses/Form', [
            'pageTitle' => 'Sửa kho hàng: ' . $warehouse->name,
            'warehouse' => $warehouse,
        ]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        $this->warehouseService->update($warehouse, $request->validated());

        return redirect()
            ->route('admin.warehouses.index')
            ->with('success', 'Cập nhật kho hàng thành công.');
    }

    public function destroy(Warehouse $warehouse)
    {
        try {
            $this->warehouseService->delete($warehouse);
        } catch (ValidationException $e) {
            return back()->with('error', $e->validator->errors()->first());
        }

        return redirect()
            ->route('admin.warehouses.index')
            ->with('success', 'Xoá kho hàng thành công.');
    }
}
