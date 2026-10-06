<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_BUSINESS = 'business';
    public const TYPE_OTHER = 'other';

    public const TYPES = [self::TYPE_INDIVIDUAL, self::TYPE_BUSINESS, self::TYPE_OTHER];

    // Không SoftDeletes. Customer sẽ được SalesDocument.customer_id (nullable) tham chiếu ở phase sau;
    // khi đó CustomerService::delete() chặn xoá Customer đã có chứng từ.

    protected $fillable = [
        'customer_code',
        'name',
        'phone',
        'email',
        'customer_type',
        'address',
        'note',
    ];

    public function account(): HasOne
    {
        return $this->hasOne(CustomerAccount::class);
    }

    public function salesDocuments(): HasMany
    {
        return $this->hasMany(SalesDocument::class);
    }
}