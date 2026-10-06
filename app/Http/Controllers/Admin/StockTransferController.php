<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockTransfer\StoreStockTransferRequest;
use App\Http\Requests\StockTransfer\UpdateStockTransferRequest;
use App\Services\ProductService;
use App\Services\StockTransferService;
use App\Services\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP layer cho chuyển kho. Controller KHÔNG đụng Stock/StockLot/StockMovement/StockAllocation —
 * POST đi qua StockTransferService -> InventoryService::transferStock().
 */
class StockTransferController extends Controller
{
    public function __construct(
        protected StockTransferService $service,
        protected WarehouseService $warehouseService,
        protected ProductService $productService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status']);

        return Inertia::render('Admin/StockTransfers/Index', [
            'pageTitle' => 'Chuyển kho',
            'transfers' => $this->service->list($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/StockTransfers/Form', array_merge($this->formOptions(), [
            'pageTitle' => 'Tạo chứng từ chuyển kho',
            'transfer' => null,
        ]));
    }

    public function store(StoreStockTransferRequest $request): RedirectResponse
    {
        $transfer = $this->service->createDraft($request->validated(), $request->user()->id);

        return redirect()->route('admin.stock-transfer.show', $transfer->id)
            ->with('success', 'Đã tạo chứng từ chuyển kho (nháp).');
    }

    public function show(int $stockTransfer): Response
    {
        $transfer = $this->service->findDetail($stockTransfer);

        return Inertia::render('Admin/StockTransfers/Show', [
            'pageTitle' => 'Chuyển kho ' . $transfer->transfer_code,
            'transfer' => $transfer,
        ]);
    }

    public function edit(int $stockTransfer): Response|RedirectResponse
    {
        try {
            $transfer = $this->service->findEditable($stockTransfer);
        } catch (ValidationException $e) {
            return redirect()->route('admin.stock-transfer.show', $stockTransfer)
                ->with('error', collect($e->errors())->flatten()->first());
        }

        return Inertia::render('Admin/StockTransfers/Form', array_merge($this->formOptions(), [
            'pageTitle' => 'Sửa chứng từ ' . $transfer->transfer_code,
            'transfer' => $transfer,
        ]));
    }

    public function update(UpdateStockTransferRequest $request, int $stockTransfer): RedirectResponse
    {
        $this->service->updateDraft($stockTransfer, $request->validated());

        return redirect()->route('admin.stock-transfer.show', $stockTransfer)
            ->with('success', 'Đã cập nhật chứng từ chuyển kho.');
    }

    public function destroy(int $stockTransfer): RedirectResponse
    {
        try {
            $this->service->deleteDraft($stockTransfer);
        } catch (ValidationException $e) {
            return redirect()->route('admin.stock-transfer.show', $stockTransfer)
                ->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('admin.stock-transfer.index')
            ->with('success', 'Đã xoá chứng từ nháp.');
    }

    public function post(Request $request, int $stockTransfer): RedirectResponse
    {
        $this->service->post($stockTransfer, $request->user()->id);

        return redirect()->route('admin.stock-transfer.show', $stockTransfer)
            ->with('success', 'Đã chuyển kho thành công.');
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