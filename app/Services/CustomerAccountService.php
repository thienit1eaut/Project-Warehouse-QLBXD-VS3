<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Repositories\CustomerAccountRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business rules của CustomerAccount (foundation). Không có đăng nhập ở đây.
 */
class CustomerAccountService
{
    public function __construct(
        protected CustomerAccountRepository $accounts,
    ) {
    }

    /**
     * $data: email, password (plaintext, chỉ tồn tại trong request), status? (mặc định active).
     * Password được hash bởi cast 'hashed' của model khi gán vào password_hash.
     */
    public function create(Customer $customer, array $data): CustomerAccount
    {
        $status = $data['status'] ?? CustomerAccount::STATUS_ACTIVE;

        $this->assertValidStatus($status);

        return DB::transaction(function () use ($customer, $data, $status) {
            if ($this->accounts->findByCustomerId($customer->id) !== null) {
                throw ValidationException::withMessages([
                    'account' => 'Khách hàng này đã có tài khoản.',
                ]);
            }

            if ($this->accounts->emailExists($data['email'])) {
                throw ValidationException::withMessages([
                    'email' => 'Email tài khoản này đã được sử dụng.',
                ]);
            }

            return $this->accounts->create([
                'customer_id' => $customer->id,
                'email' => $data['email'],
                'password_hash' => $data['password'],
                'status' => $status,
            ]);
        });
    }

    public function changeStatus(Customer $customer, string $status): CustomerAccount
    {
        $this->assertValidStatus($status);

        $account = $this->accounts->findByCustomerId($customer->id);

        if ($account === null) {
            throw ValidationException::withMessages([
                'account' => 'Khách hàng này chưa có tài khoản.',
            ]);
        }

        return $this->accounts->updateStatus($account, $status);
    }

    private function assertValidStatus(string $status): void
    {
        if (! in_array($status, CustomerAccount::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Trạng thái tài khoản không hợp lệ.',
            ]);
        }
    }
}