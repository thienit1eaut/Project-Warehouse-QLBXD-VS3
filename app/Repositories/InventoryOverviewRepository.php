<?php

namespace App\Repositories;

use App\Models\Product;
use App\Support\StockStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * CHỈ ĐỌC. Nguồn dữ liệu: products + stocks (+ stock_lots để đếm lô còn hàng).
 * Không tính tồn kho thứ hai, không lưu trạng thái.
 */
class InventoryOverviewRepository
{
    private const ON_HAND = 'COALESCE(stocks.quantity_on_hand, 0)';

    /**
     * Không chọn kho  : mọi dòng Stock hiện có + mỗi sản phẩm chưa từng có tồn (warehouse = null, tồn 0).
     * Chọn kho        : mọi sản phẩm hoạt động; sản phẩm chưa có tồn ở kho đó hiện tồn 0.
     * Sản phẩm ngừng hoạt động chỉ xuất hiện khi còn tồn > 0.
     */
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $warehouseId = ! empty($filters['warehouse_id']) ? (int) $filters['warehouse_id'] : null;

        $query = Product::query()
            ->select([
                'products.id',
                'products.sku',
                'products.name',
                'products.unit_id',
                'products.category_id',
                'products.brand_id',
                'products.minimum_stock',
                'stocks.id as stock_id',
                'warehouses.id as warehouse_id',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
            ])
            ->selectRaw(self::ON_HAND . ' as on_hand')
            ->selectRaw('(select COALESCE(SUM(s2.quantity_on_hand), 0) from stocks s2 where s2.product_id = products.id) as product_total')
            ->selectRaw('(select COUNT(*) from stock_lots sl where sl.stock_id = stocks.id and sl.quantity_remaining > 0) as lots_count')
            ->with(['category:id,name', 'brand:id,name', 'unit:id,name,code']);

        if ($warehouseId !== null) {
            $query
                ->leftJoin('stocks', function ($join) use ($warehouseId) {
                    $join->on('stocks.product_id', '=', 'products.id')
                        ->where('stocks.warehouse_id', '=', $warehouseId);
                })
                ->leftJoin('warehouses', function ($join) use ($warehouseId) {
                    $join->on('warehouses.id', '=', DB::raw($warehouseId));
                });
        } else {
            $query
                ->leftJoin('stocks', 'stocks.product_id', '=', 'products.id')
                ->leftJoin('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id');
        }

        $query->where(function (Builder $q) {
            $q->where('products.is_active', true)
                ->orWhereRaw(self::ON_HAND . ' > 0');
        });

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('products.sku', 'like', "%{$search}%")
                    ->orWhere('products.name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('products.category_id', (int) $filters['category_id']);
        }

        if (! empty($filters['brand_id'])) {
            $query->where('products.brand_id', (int) $filters['brand_id']);
        }

        if (! empty($filters['status'])) {
            $this->applyStatus($query, $filters['status']);
        }

        return $query
            ->orderBy('products.name')
            ->orderBy('warehouses.name')
            ->orderBy('products.id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Điều kiện SQL tương ứng StockStatus::resolve(). */
    private function applyStatus(Builder $query, string $status): void
    {
        match ($status) {
            StockStatus::OUT_OF_STOCK => $query->whereRaw(self::ON_HAND . ' = 0'),
            StockStatus::LOW => $query
                ->whereRaw(self::ON_HAND . ' > 0')
                ->whereRaw(self::ON_HAND . ' < products.minimum_stock'),
            StockStatus::NORMAL => $query
                ->whereRaw(self::ON_HAND . ' > 0')
                ->whereRaw(self::ON_HAND . ' >= products.minimum_stock'),
            default => null,
        };
    }
}