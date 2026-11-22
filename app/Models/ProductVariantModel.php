<?php

declare(strict_types=1);

namespace App\Models;

/**
 * product_variant: named variants of a product (e.g. "0.3 mm x 6 m") with their own SKU, price and optional picture.
 * The SKU is <product SKU>-NN, generated once (ProductService::saveVariant) and never edited.
 */
class ProductVariantModel extends BaseModel
{
    protected $table         = 'product_variant';
    protected $allowedFields = ['product_id', 'pv_sku', 'pv_name', 'pv_price', 'pv_min_price', 'image_path', 'is_active', 'sort_order'];
    protected array $nullable  = ['image_path', 'pv_min_price'];
    protected array $lowercase = ['pv_name'];
    protected array $fieldLabels = ['pv_name' => 'Variant name', 'pv_price' => 'Price', 'pv_sku' => 'SKU'];

    protected array $referencedBy = [
        'client_proposal_item' => 'variant_id',
        'invoice_item'         => 'variant_id',
        'credit_note_item'     => 'variant_id',
    ];

    protected $validationRules = [
        'product_id' => 'required|is_natural_no_zero',
        'pv_sku'     => 'required|max_length[40]|is_unique[product_variant.pv_sku,id,{id}]',
        'pv_name'    => 'required|max_length[128]',
        'pv_price'   => 'required|decimal|greater_than_equal_to[0]',
        'pv_min_price' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'is_active'  => 'permit_empty|in_list[0,1]',
        'sort_order' => 'permit_empty|is_natural',
    ];

    /**
     * Variants of a product in display order.
     *
     * @param int  $productId  Product id.
     * @param bool $activeOnly Only active variants.
     *
     * @return list<array<string, mixed>>
     */
    public function forProduct(int $productId, bool $activeOnly = false): array
    {
        $this->where('product_id', $productId)->orderBy('sort_order')->orderBy('id');

        if ($activeOnly) {
            $this->where('is_active', 1);
        }

        return $this->findAll();
    }

    /**
     * Variants of several products (for the product picker), grouped by product id.
     *
     * @param list<int> $productIds Product ids.
     * @param bool      $activeOnly Only active variants.
     *
     * @return array<int, list<array<string, mixed>>>
     */
    public function forProducts(array $productIds, bool $activeOnly): array
    {
        if ($productIds === []) {
            return [];
        }

        $this->whereIn('product_id', $productIds)->orderBy('sort_order')->orderBy('id');

        if ($activeOnly) {
            $this->where('is_active', 1);
        }

        $grouped = [];

        foreach ($this->findAll() as $row) {
            $grouped[(int) $row['product_id']][] = $row;
        }

        return $grouped;
    }

    /**
     * Number of variants (active only, or all non-deleted) per product.
     *
     * @param list<int> $productIds Product ids.
     *
     * @return array<int, int> product id => active variant count
     */
    public function activeCounts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $rows = $this->select('product_id, COUNT(*) AS n')->whereIn('product_id', $productIds)->where('is_active', 1)
            ->groupBy('product_id')->findAll();

        return array_map('intval', array_column($rows, 'n', 'product_id'));
    }
}
