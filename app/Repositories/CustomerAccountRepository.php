<?php

namespace App\Repositories;

use App\Models\CustomerAccount;

class CustomerAccountRepository
{
    public function findByCustomerId(int $customerId): ?CustomerAccount
    {
        return CustomerAccount::where('customer_id', $customerId)->first();
    }

    public function emailExists(string $email): bool
    {
        return CustomerAccount::where('email', $email)->exists();
    }

    public function create(array $data): CustomerAccount
    {
        return CustomerAccount::create($data);
    }

    public function updateStatus(CustomerAccount $account, string $status): CustomerAccount
    {
        $account->update(['status' => $status]);

        return $account->fresh();
    }
}