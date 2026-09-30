<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AdjustStockRequest;
use App\Http\Requests\Stock\IssueStockRequest;
use App\Http\Requests\Stock\ReceiveStockRequest;
use App\Services\InventoryService;
use App\Services\ProductService;
use App\Services\WarehouseService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP layer cho nghiệp vụ Inventory (Phase H). Controller CHỈ: nhận
 * FormRequest đã validate input -> gọi InventoryService -> redirect.
 * KHÔNG chứa FIFO/StockLot consumption/StockAllocation/tính toán tồn kho —
 * toàn bộ business logic đó thuộc InventoryService (đã verify Phase A-G,
 * KHÔNG đụng tới ở đây).
 */
class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected WarehouseService $warehouseService,
        protected ProductService $productService,
    ) {
    }

    /**
     * Trang landing của Inventory: chọn hướng Nhập/Xuất/Điều chỉnh.
     * Xem tồn kho chi tiết vẫn dùng trang có sẵn (StockController - admin.stock.*),
     * không lặp lại ở đây.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Inventory/Index', [
            'pageTitle' => 'Nghiệp vụ tồn kho',
        ]);
    }

    public function createReceive(): Response
    {
        return Inertia::render('Admin/Inventory/Receive', [
            'pageTitle' => 'Nhập kho',
            'warehouseOptions' => $this->warehouseService->options(),
            'productOptions' => $this->productService->options(),
        ]);
    }

    public function storeReceive(ReceiveStockRequest $request)
    {
        $data = $request->validated();

        $this->inventoryService->receiveStock(
            (int) $data['warehouse_id'],
            (int) $data['product_id'],
            (float) $data['quantity'],
            $data['received_at'] ?? null,
            $data['expiry_date'] ?? null,
            ['note' => $data['note'] ?? null]
        );

        return redirect()
            ->route('admin.inventory.index')
            ->with('success', 'Nhập kho thành công.');
    }

    public function createIssue(): Response
    {
        return Inertia::render('Admin/Inventory/Issue', [
            'pageTitle' => 'Xuất kho',
            'warehouseOptions' => $this->warehouseService->options(),
            'productOptions' => $this->productService->options(),
        ]);
    }

    public function storeIssue(IssueStockRequest $request)
    {
        $data = $request->validated();

        // InventoryService::issueStock() tự lock Stock/StockLot, chạy FIFO,
        // tạo StockAllocation, hoặc reject (ValidationException) nếu không đủ
        // tồn — Controller không tự quyết định gì, chỉ chuyển tiếp input.
        $this->inventoryService->issueStock(
            (int) $data['warehouse_id'],
            (int) $data['product_id'],
            (float) $data['quantity'],
            ['note' => $data['note'] ?? null]
        );

        return redirect()
            ->route('admin.inventory.index')
            ->with('success', 'Xuất kho thành công.');
    }

    public function createAdjustment(): Response
    {
        return Inertia::render('Admin/Inventory/Adjustment', [
            'pageTitle' => 'Điều chỉnh tồn kho',
            'warehouseOptions' => $this->warehouseService->options(),
            'productOptions' => $this->productService->options(),
        ]);
    }

    public function storeAdjustment(AdjustStockRequest $request)
    {
        $data = $request->validated();

        // actual_quantity là TỔNG tồn thực tế sau kiểm kê, KHÔNG phải delta -
        // đúng signature InventoryService::adjustStock(). Service tự tính
        // difference, tự tạo lot mới (tăng) hoặc consume FIFO (giảm).
        $this->inventoryService->adjustStock(
            (int) $data['warehouse_id'],
            (int) $data['product_id'],
            (float) $data['actual_quantity'],
            ['note' => $data['note'] ?? null]
        );

        return redirect()
            ->route('admin.inventory.index')
            ->with('success', 'Điều chỉnh tồn kho thành công.');
    }
}