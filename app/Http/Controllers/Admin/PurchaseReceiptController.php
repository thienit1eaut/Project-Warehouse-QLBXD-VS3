<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseReceipt\StorePurchaseReceiptRequest;
use App\Services\ProductService;
use App\Services\PurchaseReceiptService;
use App\Services\SupplierService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP layer cho Purchase Receipt. Controller KHÔNG đụng Stock/StockLot/StockMovement —
 * POST đi qua PurchaseReceiptService -> InventoryService::receiveStock().
 */
class PurchaseReceiptController extends Controller
{
    public function __construct(
        protected PurchaseReceiptService $service,
        protected SupplierService $supplierService,
        protected WarehouseService $warehouseService,
        protected ProductService $productService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status']);

        return Inertia::render('Admin/PurchaseReceipts/Index', [
            'pageTitle' => 'Phiếu nhập kho',
            'receipts' => $this->service->list($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PurchaseReceipts/Create', [
            'pageTitle' => 'Tạo phiếu nhập kho',
            'supplierOptions' => $this->supplierService->options(),
            'warehouseOptions' => $this->warehouseService->options(),
            'productOptions' => $this->productService->options(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(StorePurchaseReceiptRequest $request)
    {
        $receipt = $this->service->createDraft($request->validated(), $request->user()->id);

        return redirect()
            ->route('admin.purchase-receipts.show', $receipt->id)
            ->with('success', 'Đã tạo phiếu nhập kho (nháp).');
    }

    public function show(int $receipt): Response
    {
        $receipt = $this->service->find($receipt);

        return Inertia::render('Admin/PurchaseReceipts/Show', [
            'pageTitle' => 'Phiếu nhập kho ' . $receipt->receipt_code,
            'receipt' => $receipt,
        ]);
    }

    public function post(Request $request, int $receipt)
    {
        $this->service->post($receipt, $request->user()->id);

        return redirect()
            ->route('admin.purchase-receipts.show', $receipt)
            ->with('success', 'Đã nhập kho thành công.');
    }
}