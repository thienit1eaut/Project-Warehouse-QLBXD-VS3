<?php

namespace App\Services;

use App\Repositories\InventoryDashboardRepository;
use App\Repositories\InventoryOverviewRepository;
use App\Support\StockStatus;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Dashboard + Inventory Overview — CHỈ ĐỌC. Không gọi InventoryService, không ghi dữ liệu.
 */
class InventoryMonitoringService
{
    private const REFERENCES = [
        'purchase_receipt' => ['label' => 'Phiếu nhập', 'url' => '/admin/purchase-receipts/%d', 'permission' => 'purchase-receipt.view'],
        'sales_document' => ['label' => 'Chứng từ bán', 'url' => '/admin/sales/%d', 'permission' => 'sales-document.view'],
        'stock_transfer' => ['label' => 'Chuyển kho', 'url' => '/admin/stock-transfer/%d', 'permission' => 'stock-transfer.view'],
        'stocktake' => ['label' => 'Phiếu kiểm kê', 'url' => '/admin/stocktake/%d', 'permission' => 'stocktake.view'],
    ];

    public function __construct(
        protected InventoryDashboardRepository $dashboard,
        protected InventoryOverviewRepository $overview,
    ) {
    }

    public function dashboard(): array
    {
        $drafts = $this->dashboard->countDraftDocuments();
        $drafts['total'] = array_sum($drafts);

        return [
            'kpis' => [
                'total_products' => $this->dashboard->countActiveProducts(),
                'total_warehouses' => $this->dashboard->countActiveWarehouses(),
                'total_on_hand' => $this->dashboard->totalOnHand(),
                'out_of_stock_products' => $this->dashboard->countOutOfStockProducts(),
                'low_stock_products' => $this->dashboard->countLowStockProducts(),
            ],
            'drafts' => $drafts,
            'recent_movements' => $this->recentMovements(),
        ];
    }

    public function overview(array $filters = []): LengthAwarePaginator
    {
        if (! empty($filters['status']) && ! in_array($filters['status'], StockStatus::ALL, true)) {
            unset($filters['status']); // giá trị lạ => bỏ qua filter, không lỗi
        }

        return $this->overview->paginate($filters)->through(function ($row) {
            $onHand = (float) $row->on_hand;
            $minimum = (float) $row->minimum_stock;

            return [
                'product_id' => (int) $row->id,
                'sku' => $row->sku,
                'name' => $row->name,
                'category' => $row->category?->name,
                'brand' => $row->brand?->name,
                'unit' => $row->unit?->name,
                'stock_id' => $row->stock_id !== null ? (int) $row->stock_id : null,
                'warehouse' => $row->warehouse_id !== null ? [
                    'id' => (int) $row->warehouse_id,
                    'code' => $row->warehouse_code,
                    'name' => $row->warehouse_name,
                ] : null,
                'on_hand' => $onHand,
                'product_total' => (float) $row->product_total,
                'lots_count' => (int) $row->lots_count,
                'minimum_stock' => $minimum,
                'status' => StockStatus::resolve($onHand, $minimum),
            ];
        });
    }

    private function recentMovements(): array
    {
        $movements = $this->dashboard->recentMovements(10);

        $idsByType = [];
        foreach ($movements as $movement) {
            if ($movement->reference_type !== null && $movement->reference_id !== null) {
                $idsByType[$movement->reference_type][] = (int) $movement->reference_id;
            }
        }

        $codes = $this->dashboard->referenceCodes($idsByType);

        return $movements->map(function ($movement) use ($codes) {
            $reference = null;
            $config = self::REFERENCES[$movement->reference_type] ?? null;
            $code = $codes[$movement->reference_type][(int) $movement->reference_id] ?? null;

            if ($config !== null && $code !== null) {
                $reference = [
                    'label' => $config['label'],
                    'code' => $code,
                    'url' => sprintf($config['url'], $movement->reference_id),
                    'permission' => $config['permission'],
                ];
            }

            return [
                'id' => $movement->id,
                'created_at' => $movement->created_at?->toDateTimeString(),
                'movement_type' => $movement->movement_type,
                'type_label' => $this->movementLabel($movement->movement_type, $movement->reference_type),
                'quantity' => (float) $movement->quantity, // có dấu: IN > 0, OUT < 0
                'product' => $movement->product ? ['sku' => $movement->product->sku, 'name' => $movement->product->name] : null,
                'warehouse' => $movement->warehouse ? ['code' => $movement->warehouse->code, 'name' => $movement->warehouse->name] : null,
                'reference' => $reference,
            ];
        })->all();
    }

    private function movementLabel(string $type, ?string $referenceType): string
    {
        return match (true) {
            $type === 'in' && $referenceType === 'purchase_receipt' => 'Nhập kho',
            $type === 'in' && $referenceType === 'stock_transfer' => 'Chuyển kho (nhập)',
            $type === 'out' && $referenceType === 'sales_document' => 'Xuất bán',
            $type === 'out' && $referenceType === 'stock_transfer' => 'Chuyển kho (xuất)',
            $type === 'adjustment' && $referenceType === 'stocktake' => 'Kiểm kê',
            $type === 'in' => 'Nhập kho',
            $type === 'out' => 'Xuất kho',
            $type === 'adjustment' => 'Điều chỉnh',
            default => $type,
        };
    }
}