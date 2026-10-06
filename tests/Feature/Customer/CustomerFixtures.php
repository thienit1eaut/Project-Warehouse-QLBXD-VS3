<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\CustomerAccount;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

trait CustomerFixtures
{
    protected User $admin;
    protected User $viewer;
    protected User $noAccess;

    protected function createCustomerFixtures(): void
    {
        $this->admin = $this->makeUser('admin@example.com', 'cust-admin', [
            'customer.view', 'customer.create', 'customer.update', 'customer.delete',
        ]);
        $this->viewer = $this->makeUser('viewer@example.com', 'cust-viewer', ['customer.view']);
        $this->noAccess = $this->makeUser('none@example.com', 'cust-none', []);
    }

    protected function makeUser(string $email, string $roleSlug, array $permissionKeys): User
    {
        $role = Role::create(['name' => $roleSlug, 'slug' => $roleSlug]);

        $ids = collect($permissionKeys)->map(function (string $key) {
            [$moduleSlug, $action] = explode('.', $key, 2);
            $module = Module::firstOrCreate(['slug' => $moduleSlug], ['name' => $moduleSlug]);

            return Permission::firstOrCreate(
                ['module_id' => $module->id, 'action' => $action],
                ['name' => $key]
            )->id;
        });

        $role->permissions()->attach($ids);

        return User::create([
            'name' => $email,
            'email' => $email,
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    protected function makeCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'customer_code' => 'KH' . random_int(10000, 99999),
            'name' => 'Khách hàng test',
            'customer_type' => Customer::TYPE_INDIVIDUAL,
        ], $overrides));
    }

    protected function makeAccount(Customer $customer, array $overrides = []): CustomerAccount
    {
        return CustomerAccount::create(array_merge([
            'customer_id' => $customer->id,
            'email' => 'account' . $customer->id . '@example.com',
            'password_hash' => 'Secret123',
            'status' => CustomerAccount::STATUS_ACTIVE,
        ], $overrides));
    }

    protected function customerPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_code' => 'KH001',
            'name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
            'email' => 'a@example.com',
            'customer_type' => 'individual',
            'address' => 'Hà Nội',
            'note' => null,
        ], $overrides);
    }
}