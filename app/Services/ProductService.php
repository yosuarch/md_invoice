<?php

declare(strict_types=1);

namespace App\Services;

use App\Libraries\ImageUpload;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

/**
 * Products with picture and variants.
 * - The product SKU comes from the SKU numbering (Settings → Document numbering), generated once.
 * - Variant SKU = <product SKU>-NN (NN counts every variant ever created for the product, so numbers are never reused).
 * - Pictures live in writable/uploads/products/ (ImageUpload); replacing or removing one deletes the old file
 *   after the database change succeeded.
 */
final class ProductService
{
    public const IMAGE_FOLDER = 'products';

    private ProductModel $products;
    private ProductVariantModel $variants;

    public function __construct()
    {
        $this->products = new ProductModel();
        $this->variants = new ProductVariantModel();
    }

    /**
     * Create a product (SKU generated in the same transaction).
     *
     * @param array<string, mixed> $data  Product fields (without pl_sku / image_path).
     * @param UploadedFile|null    $image Optional picture.
     *
     * @return array{id: int, sku: string}
     *
     * @throws ValidationFailed Field errors.
     */
    public function create(array $data, ?UploadedFile $image): array
    {
        $this->checkImage($image, 'image');
        $stored = ImageUpload::present($image) ? ImageUpload::store($image, self::IMAGE_FOLDER) : null;

        try {
            return Transaction::run(function () use ($data, $stored): array {
                $data['pl_sku']     = (new DocumentSequence())->next('SKU');
                $data['image_path'] = $stored;

                if ($this->products->insert($data) === false) {
                    throw new ValidationFailed($this->products->errors());
                }

                return ['id' => (int) $this->products->getInsertID(), 'sku' => $data['pl_sku']];
            });
        } catch (\Throwable $e) {
            ImageUpload::remove($stored, self::IMAGE_FOLDER);

            throw $e;
        }
    }

    /**
     * Update a product; optionally replace or remove its picture. The SKU never changes.
     *
     * @param int                  $id          Product id.
     * @param array<string, mixed> $data        Product fields.
     * @param UploadedFile|null    $image       New picture (replaces the old one).
     * @param bool                 $removeImage Remove the current picture.
     *
     * @return void
     *
     * @throws ValidationFailed Field errors.
     */
    public function update(int $id, array $data, ?UploadedFile $image, bool $removeImage): void
    {
        $product = $this->products->find($id) ?? throw new RuntimeException(lang('Products.notFound'));
        $this->checkImage($image, 'image');
        $stored = ImageUpload::present($image) ? ImageUpload::store($image, self::IMAGE_FOLDER) : null;

        $data['id']     = $id;
        $data['pl_sku'] = $product['pl_sku'];

        if ($stored !== null || $removeImage) {
            $data['image_path'] = $stored;
        }

        if (! $this->products->update($id, $data)) {
            ImageUpload::remove($stored, self::IMAGE_FOLDER);

            throw new ValidationFailed($this->products->errors());
        }

        if ($stored !== null || $removeImage) {
            ImageUpload::remove($product['image_path'], self::IMAGE_FOLDER);
        }
    }

    /**
     * Delete a product no document uses, with its variants (soft delete; pictures are kept with the rows).
     *
     * @param int $id Product id.
     *
     * @return void
     *
     * @throws RuntimeException When documents use the product.
     */
    public function delete(int $id): void
    {
        if ($this->products->isInUse($id)) {
            throw new RuntimeException(lang('Products.inUseDelete'));
        }

        Transaction::run(function () use ($id): void {
            foreach ($this->variants->forProduct($id) as $variant) {
                $this->variants->delete((int) $variant['id']);
            }

            $this->products->delete($id);
        });
    }

    /**
     * Create or update a variant.
     *
     * @param int                  $productId   Product id.
     * @param int|null             $variantId   Variant id (null = new).
     * @param array<string, mixed> $data        pv_name, pv_price, is_active, sort_order.
     * @param UploadedFile|null    $image       New picture.
     * @param bool                 $removeImage Remove the current picture.
     *
     * @return int Variant id.
     *
     * @throws ValidationFailed Field errors.
     */
    public function saveVariant(int $productId, ?int $variantId, array $data, ?UploadedFile $image, bool $removeImage): int
    {
        $this->checkImage($image, 'variant_image');
        $data = array_intersect_key($data, array_flip(['pv_name', 'pv_price', 'pv_min_price', 'is_active', 'sort_order']));
        $name = normalize_name((string) ($data['pv_name'] ?? ''));

        if ($name !== '' && $this->nameTaken($productId, $name, $variantId)) {
            throw new ValidationFailed(['pv_name' => lang('Products.variantNameTaken')]);
        }

        $stored = ImageUpload::present($image) ? ImageUpload::store($image, self::IMAGE_FOLDER) : null;

        try {
            [$id, $oldImage] = Transaction::run(function ($db) use ($productId, $variantId, $data, $stored, $removeImage): array {
                // Lock the product row: variant numbers are counted per product.
                $product = $db->query('SELECT id, pl_sku FROM product_list WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [$productId])->getRowArray()
                    ?? throw new RuntimeException(lang('Products.notFound'));

                if ($stored !== null || $removeImage) {
                    $data['image_path'] = $stored;
                }

                if ($variantId === null) {
                    $count = (int) $db->table('product_variant')->where('product_id', $productId)->countAllResults();
                    $data += ['product_id' => $productId, 'pv_sku' => $product['pl_sku'] . '-' . str_pad((string) ($count + 1), 2, '0', STR_PAD_LEFT)];

                    if ($this->variants->insert($data) === false) {
                        throw new ValidationFailed($this->variants->errors());
                    }

                    return [(int) $this->variants->getInsertID(), null];
                }

                $variant = $this->variants->where('product_id', $productId)->find($variantId) ?? throw new RuntimeException(lang('Products.variantNotFound'));
                $data   += ['id' => $variantId, 'product_id' => $productId, 'pv_sku' => $variant['pv_sku']];

                if (! $this->variants->update($variantId, $data)) {
                    throw new ValidationFailed($this->variants->errors());
                }

                return [$variantId, ($stored !== null || $removeImage) ? $variant['image_path'] : null];
            });
        } catch (\Throwable $e) {
            ImageUpload::remove($stored, self::IMAGE_FOLDER);

            throw $e;
        }

        ImageUpload::remove($oldImage, self::IMAGE_FOLDER);

        return $id;
    }

    /**
     * Delete a variant that no document uses (soft delete).
     *
     * @param int $productId Product id.
     * @param int $variantId Variant id.
     *
     * @return void
     *
     * @throws RuntimeException When documents use the variant.
     */
    public function deleteVariant(int $productId, int $variantId): void
    {
        $variant = $this->variants->where('product_id', $productId)->find($variantId) ?? throw new RuntimeException(lang('Products.variantNotFound'));

        if ($this->variants->isInUse((int) $variant['id'])) {
            throw new RuntimeException(lang('Products.variantInUse'));
        }

        $this->variants->delete((int) $variant['id']);
    }

    /**
     * Whether another non-deleted variant of the product has this name.
     *
     * @param int      $productId Product id.
     * @param string   $name      Normalized name.
     * @param int|null $exceptId  Variant being edited.
     *
     * @return bool
     */
    private function nameTaken(int $productId, string $name, ?int $exceptId): bool
    {
        $builder = $this->variants->where('product_id', $productId)->where('pv_name', $name);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Throw a field error when an uploaded picture is invalid.
     *
     * @param UploadedFile|null $image Uploaded file.
     * @param string            $field Form field name for the error.
     *
     * @return void
     *
     * @throws ValidationFailed
     */
    private function checkImage(?UploadedFile $image, string $field): void
    {
        if (ImageUpload::present($image) && ($error = ImageUpload::check($image)) !== null) {
            throw new ValidationFailed([$field => $error]);
        }
    }
}
