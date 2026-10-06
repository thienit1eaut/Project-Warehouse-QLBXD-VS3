<?php

namespace App\Repositories;

use App\Models\Customer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CustomerRepository
{
    private const SORTABLE = ['customer_code', 'name', 'created_at'];

    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return Customer::query()
            ->with('account:id,customer_id,status')
            ->when(
                $filters['search'] ?? null,
                fn ($q, $search) => $q->where(function ($sub) use ($search) {
                    $sub->where('customer_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
            )
            ->when(
                $filters['customer_type'] ?? null,
                fn ($q, $type) => $q->where('customer_type', $type)
            )
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findWithAccount(int $id): Customer
    {
        return Customer::with('account:id,customer_id,email,email_verified_at,status')->findOrFail($id);
    }

    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);

        return $customer->fresh();
    }

    public function delete(Customer $customer): bool
    {
        return (bool) $customer->delete();
    }

    public function hasAccount(Customer $customer): bool
    {
        return $customer->account()->exists();
    }

    public function options(): \Illuminate\Database\Eloquent\Collection
    {
        return Customer::query()->orderBy('name')->get(['id', 'customer_code', 'name']);
    }

    public function isUsedBySalesDocument(Customer $customer): bool
    {
        return $customer->salesDocuments()->exists();
    }
}