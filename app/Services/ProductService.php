<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        protected ProductRepository $productRepository,
    ) {}

    public function list(array $filters)
    {
        return $this->productRepository->paginate($filters);
    }

    public function find(int $id): Product
    {
        return $this->productRepository->findById($id);
    }

    /**
     * $data['img'] là media_id (int, nullable) — đã validate tồn tại +
     * type=image ở StoreProductRequest. Không xử lý file upload ở đây,
     * cùng nguyên tắc Brand/Category: Product chỉ tham chiếu Media.
     */
    public function create(array $data): Product
    {
        $data['slug'] = $this->generateUniqueSlug($data['name']);

        return $this->productRepository->create($data);
    }

    public function update(Product $product, array $data): Product
    {
        if (($data['name'] ?? null) !== $product->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], excludeId: $product->id);
        }

        return $this->productRepository->update($product, $data);
    }

    /**
     * Hard delete — Product không dùng SoftDeletes, đúng convention
     * Category/Brand/Supplier/Unit (không module master data nào trong
     * project hiện dùng soft-delete/restore).
     */
    public function delete(Product $product): void
    {
        $this->productRepository->delete($product);
    }

    protected function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Product::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}