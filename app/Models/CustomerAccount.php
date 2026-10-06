<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nền tảng dữ liệu tài khoản khách hàng cho Ecommerce TƯƠNG LAI.
 *
 * Cố ý là Model thường, KHÔNG phải Authenticatable và KHÔNG có guard: hệ thống
 * back-office này không có đăng nhập cho khách. Cơ chế xác thực của website
 * (session/token...) sẽ được thiết kế ở phase Ecommerce/API.
 */
class CustomerAccount extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'customer_id',
        'email',
        'password_hash',
        'email_verified_at',
        'status',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected function casts(): array
    {
        return [
            // Cast 'hashed': gán plaintext vào password_hash sẽ tự được hash chuẩn Laravel,
            // không bao giờ lưu plaintext.
            'password_hash' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}