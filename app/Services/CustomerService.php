<?php

namespace App\Services;

use App\Models\Customer;
use App\Repositories\CustomerRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(
        protected CustomerRepository $customers,
    ) {
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->customers->paginate($filters);
    }

    public function findDetail(int $id): Customer
    {
        return $this->customers->findWithAccount($id);
    }

    public function options(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->customers->options();
    }

    public function create(array $data): Customer
    {
        return $this->customers->create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        return $this->customers->update($customer, $data);
    }

    public function delete(Customer $customer): void
    {
        // Ưu tiên bảo vệ dữ liệu: không cho xoá Customer đã có account, không cascade âm thầm.
        if ($this->customers->hasAccount($customer)) {
            throw ValidationException::withMessages([
                'customer' => 'Không thể xoá khách hàng đã có tài khoản. Hãy chuyển tài khoản sang trạng thái inactive thay vì xoá.',
            ]);
        }

        if ($this->customers->isUsedBySalesDocument($customer)) {
            throw ValidationException::withMessages([
                'customer' => 'Không thể xoá khách hàng đã có chứng từ bán hàng.',
            ]);
        }

        $this->customers->delete($customer);
    }
}