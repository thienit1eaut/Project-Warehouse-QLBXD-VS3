<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\PurchaseReceipt;
use App\Models\SalesDocument;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Stocktake;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;

/** CHỈ ĐỌC. Mọi con số lấy trực tiếp từ dữ liệu hiện có, không cache, không bảng mới. */
class InventoryDashboardRepository
{
    private const PRODUCT_TOTAL = '(select COALESCE(SUM(s.quantity_on_hand), 0) from stocks s where s.product_id = products.id)';

    public function countActiveProducts(): int
    {
        return Product::where('is_active', true)->count();
    }

    public function countActiveWarehouses(): int
    {
        return Warehouse::where('is_active', true)->count();
    }

    /** Tổng tồn lấy từ Stock (nguồn tồn hiện tại), không tính từ StockLot. */
    public function totalOnHand(): float
    {
        return (float) Stock::sum('quantity_on_hand');
    }

    /** Sản phẩm hoạt động có TỔNG tồn trên mọi kho = 0 (kể cả chưa từng có Stock). */
    public function countOutOfStockProducts(): int
    {
        return Product::where('is_active', true)
            ->whereRaw(self::PRODUCT_TOTAL . ' = 0')
            ->count();
    }

    /** 0 < tổng tồn < minimum_stock. minimum_stock = 0 thì không bao giờ LOW. */
    public function countLowStockProducts(): int
    {
        return Product::where('is_active', true)
            ->whereRaw(self::PRODUCT_TOTAL . ' > 0')
            ->whereRaw(self::PRODUCT_TOTAL . ' < products.minimum_stock')
            ->count();
    }

    /** @return array<string, int> */
    public function countDraftDocuments(): array
    {
        return [
            'purchase_receipts' => PurchaseReceipt::where('status', PurchaseReceipt::STATUS_DRAFT)->count(),
            'sales_documents' => SalesDocument::where('status', SalesDocument::STATUS_DRAFT)->count(),
            'stock_transfers' => StockTransfer::where('status', StockTransfer::STATUS_DRAFT)->count(),
            'stocktakes' => Stocktake::where('status', Stocktake::STATUS_DRAFT)->count(),
        ];
    }

    public function recentMovements(int $limit = 10): Collection
    {
        return StockMovement::query()
            ->with(['product:id,sku,name', 'warehouse:id,code,name'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Mã chứng từ cho các reference của movement — gom theo loại (tối đa 4 query, không N+1).
     *
     * @param  array<string, int[]>  $idsByType
     * @return array<string, array<int, string>>  [reference_type => [id => code]]
     */
    public function referenceCodes(array $idsByType): array
    {
        $map = [
            'purchase_receipt' => [PurchaseReceipt::class, 'receipt_code'],
            'sales_document' => [SalesDocument::class, 'document_code'],
            'stock_transfer' => [StockTransfer::class, 'transfer_code'],
            'stocktake' => [Stocktake::class, 'stocktake_code'],
        ];

        $codes = [];

        foreach ($idsByType as $type => $ids) {
            if (! isset($map[$type]) || $ids === []) {
                continue;
            }

            [$model, $column] = $map[$type];

            $codes[$type] = $model::query()->whereIn('id', $ids)->pluck($column, 'id')->all();
        }

        return $codes;
    }
}