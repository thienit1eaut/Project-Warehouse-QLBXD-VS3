<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stocktake\StoreStocktakeRequest;
use App\Http\Requests\Stocktake\UpdateStocktakeRequest;
use App\Services\ProductService;
use App\Services\StocktakeService;
use App\Services\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP layer cho phiếu kiểm kê. Controller KHÔNG đụng Stock/StockLot/StockMovement/StockAllocation —
 * POST đi qua StocktakeService -> InventoryService::adjustStock().
 */
class StocktakeController extends Controller
{
    public function __construct(
        protected StocktakeService $service,
        protected WarehouseService $warehouseService,
        protected ProductService $productService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status']);

        return Inertia::render('Admin/Stocktakes/Index', [
            'pageTitle' => 'Phiếu kiểm kê',
            'stocktakes' => $this->service->list($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Stocktakes/Form', array_merge($this->formOptions(), [
            'pageTitle' => 'Tạo phiếu kiểm kê',
            'stocktake' => null,
        ]));
    }

    public function store(StoreStocktakeRequest $request): RedirectResponse
    {
        $stocktake = $this->service->createDraft($request->validated(), $request->user()->id);

        return redirect()->route('admin.stocktake.show', $stocktake->id)
            ->with('success', 'Đã tạo phiếu kiểm kê (nháp).');
    }

    public function show(int $stocktake): Response
    {
        $stocktake = $this->service->findDetail($stocktake);

        return Inertia::render('Admin/Stocktakes/Show', [
            'pageTitle' => 'Phiếu kiểm kê ' . $stocktake->stocktake_code,
            'stocktake' => $stocktake,
        ]);
    }

    public function edit(int $stocktake): Response|RedirectResponse
    {
        try {
            $document = $this->service->findEditable($stocktake);
        } catch (ValidationException $e) {
            return redirect()->route('admin.stocktake.show', $stocktake)
                ->with('error', collect($e->errors())->flatten()->first());
        }

        return Inertia::render('Admin/Stocktakes/Form', array_merge($this->formOptions(), [
            'pageTitle' => 'Sửa phiếu ' . $document->stocktake_code,
            'stocktake' => $document,
        ]));
    }

    public function update(UpdateStocktakeRequest $request, int $stocktake): RedirectResponse
    {
        $this->service->updateDraft($stocktake, $request->validated());

        return redirect()->route('admin.stocktake.show', $stocktake)
            ->with('success', 'Đã cập nhật phiếu kiểm kê (đã chụp lại tồn hệ thống).');
    }

    public function destroy(int $stocktake): RedirectResponse
    {
        try {
            $this->service->deleteDraft($stocktake);
        } catch (ValidationException $e) {
            return redirect()->route('admin.stocktake.show', $stocktake)
                ->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('admin.stocktake.index')
            ->with('success', 'Đã xoá phiếu kiểm kê nháp.');
    }

    public function post(Request $request, int $stocktake): RedirectResponse
    {
        $this->service->post($stocktake, $request->user()->id);

        return redirect()->route('admin.stocktake.show', $stocktake)
            ->with('success', 'Đã chốt phiếu kiểm kê và điều chỉnh tồn kho.');
    }

    private function formOptions(): array
    {
        return [
            'warehouseOptions' => $this->warehouseService->options(),
            'productOptions' => $this->productService->options(),
            'today' => now()->toDateString(),
        ];
    }
}