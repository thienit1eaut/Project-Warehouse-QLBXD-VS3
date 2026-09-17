<?php

namespace App\Services;

use App\Models\Supplier;
use App\Repositories\SupplierRepository;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function __construct(
        protected SupplierRepository $supplierRepository,
    ) {}
 
    public function list(array $filters)
    {
        return $this->supplierRepository->paginate($filters);
    }
 
    public function options()
    {
        return $this->supplierRepository->all();
    }
 
    public function find(int $id): Supplier
    {
        return $this->supplierRepository->findById($id);
    }
 
    public function create(array $data): Supplier
    {
        return $this->supplierRepository->create($data);
    }
 
    public function update(Supplier $supplier, array $data): Supplier
    {
        return $this->supplierRepository->update($supplier, $data);
    }
 
    public function delete(Supplier $supplier): void
    {
        // Không cascade delete tuỳ tiện — bảo vệ toàn vẹn dữ liệu Product/Purchase Order
        if ($this->supplierRepository->isUsedByProduct($supplier)) {
            throw ValidationException::withMessages([
                'supplier' => 'Không thể xoá nhà cung cấp đang được sản phẩm sử dụng.',
            ]);
        }
 
        // if ($this->supplierRepository->isUsedByPurchaseOrder($supplier)) {
        //     throw ValidationException::withMessages([
        //         'supplier' => 'Không thể xoá nhà cung cấp đang có đơn nhập hàng liên quan.',
        //     ]);
        // }
 
        $this->supplierRepository->delete($supplier);
    }
}
