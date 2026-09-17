<?php

namespace App\Services;

use App\Models\Brand;
use App\Repositories\BrandRepository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrandService
{
    public function __construct(
        protected BrandRepository $brandRepository,
    ) {}

    public function list(array $filters)
    {
        return $this->brandRepository->paginate($filters);
    }

    public function options()
    {
        return $this->brandRepository->all();
    }

    public function find(int $id): Brand
    {
        return $this->brandRepository->findById($id);
    }

    /**
     * $data['img'] giờ là media_id (int, nullable) — đã validate tồn tại +
     * đúng type=image ở StoreBrandRequest. Không còn xử lý file upload ở
     * đây nữa; Brand chỉ tham chiếu Media, không sở hữu file vật lý.
     */
    public function create(array $data): Brand
    {
        $data['slug'] = $this->generateUniqueSlug($data['name']);

        return $this->brandRepository->create($data);
    }

    public function update(Brand $brand, array $data): Brand
    {
        if (($data['name'] ?? null) !== $brand->name) {
            $data['slug'] = $this->generateUniqueSlug($data['name'], excludeId: $brand->id);
        }

        return $this->brandRepository->update($brand, $data);
    }

    public function delete(Brand $brand): void
    {
        if ($this->brandRepository->isUsedByProduct($brand)) {
            throw ValidationException::withMessages([
                'brand' => 'Không thể xoá thương hiệu đang được sản phẩm sử dụng.',
            ]);
        }

        // Không xoá file vật lý — Brand chỉ tham chiếu Media qua FK (img),
        // vòng đời file hoàn toàn thuộc về Media module, độc lập với Brand.
        $this->brandRepository->delete($brand);
    }

    protected function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Brand::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}