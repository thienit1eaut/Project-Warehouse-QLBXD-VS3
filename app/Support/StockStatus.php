<?php

namespace App\Support;

/**
 * Trạng thái tồn kho TÍNH RUNTIME, không lưu DB.
 *   OUT_OF_STOCK : on_hand = 0
 *   LOW          : 0 < on_hand < minimum_stock
 *   NORMAL       : on_hand > 0 và on_hand >= minimum_stock (minimum_stock = 0 => còn hàng là NORMAL)
 *
 * Điều kiện SQL tương ứng nằm ở InventoryOverviewRepository::applyStatus() — hai nơi phải khớp nhau
 * (có test đối chiếu).
 */
final class StockStatus
{
    public const OUT_OF_STOCK = 'out_of_stock';
    public const LOW = 'low';
    public const NORMAL = 'normal';

    public const ALL = [self::OUT_OF_STOCK, self::LOW, self::NORMAL];

    public static function resolve(float $onHand, float $minimumStock): string
    {
        if ($onHand <= 0) {
            return self::OUT_OF_STOCK;
        }

        return $onHand < $minimumStock ? self::LOW : self::NORMAL;
    }
}