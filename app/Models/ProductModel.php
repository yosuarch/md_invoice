<?php

declare(strict_types=1);

namespace App\Models;

/**
 * product_list: goods/services with the current default price.
 * Documents copy title/unit/type/price/tax into their item lines, so editing a product never changes old documents.
 * pl_type matters for withholding: PPh 22 applies to goods, PPh 23 to services.
 */
class ProductModel extends BaseModel
{
    public const TYPES = ['GOODS' => 'Goods', 'SERVICE' => 'Service'];

    protected $table         = 'product_list';
    protected $allowedFields = ['pl_sku', 'pl_title', 'pl_alias', 'pl_description', 'image_path', 'pl_type', 'unit_id', 'pl_price', 'pl_price_incl_tax', 'pl_min_price', 'tax_rate_id', 'is_active'];
    protected array $nullable  = ['pl_alias', 'pl_description', 'image_path', 'tax_rate_id', 'pl_min_price'];
    protected array $lowercase = ['pl_title', 'pl_alias'];
    protected array $fieldLabels = ['pl_sku' => 'SKU', 'pl_description' => 'Description', 'pl_type' => 'Type', 'pl_price' => 'Default price', 'tax_rate_id' => 'Tax'];

    protected array $referencedBy = [
        'client_proposal_item' => 'product_id',
        'invoice_item'         => 'product_id',
        'credit_note_item'     => 'product_id',
    ];

    protected $validationRules = [
        'pl_sku'      => 'required|max_length[32]|regex_match[/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/]|is_unique[product_list.pl_sku,id,{id}]',
        'pl_title'    => 'required|max_length[200]',
        'pl_alias'    => 'permit_empty|max_length[200]',
        'pl_description' => 'permit_empty|max_length[2000]',
        'pl_type'     => 'required|in_list[GOODS,SERVICE]',
        'unit_id'     => 'required|is_natural_no_zero|is_not_unique[unit_of_measurement.id]',
        'pl_price'    => 'required|decimal|greater_than_equal_to[0]',
        'pl_price_incl_tax' => 'permit_empty|in_list[0,1]',
        'pl_min_price' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'tax_rate_id' => 'permit_empty|is_natural_no_zero|is_not_unique[tax_rate.id]',
        'is_active'   => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'pl_sku' => [
            'regex_match' => 'SKU may contain letters, digits, dot, dash, underscore and slash.',
            'is_unique'   => 'This SKU is already used (including deleted products).',
        ],
    ];

    /**
     * List query with search and status filter, joined with unit and tax codes.
     *
     * @param string $search Matches SKU, title or alias.
     * @param string $status '' (all), 'active' or 'inactive'.
     *
     * @return $this
     */
    public function search(string $search, string $status): self
    {
        $this->select('product_list.*, unit_of_measurement.code AS unit_code, tax_rate.code AS tax_code, tax_rate.rate AS tax_rate')
            ->join('unit_of_measurement', 'unit_of_measurement.id = product_list.unit_id', 'left')
            ->join('tax_rate', 'tax_rate.id = product_list.tax_rate_id', 'left')
            ->orderBy('product_list.pl_title');

        if ($search !== '') {
            $this->groupStart()
                ->like('product_list.pl_sku', $search)
                ->orLike('product_list.pl_title', $search)
                ->orLike('product_list.pl_alias', $search)
                ->groupEnd();
        }

        if ($status === 'active' || $status === 'inactive') {
            $this->where('product_list.is_active', $status === 'active' ? 1 : 0);
        }

        return $this;
    }

    /**
     * What a new document line copies from products: title, description, unit, type, price and the tax rate.
     * The tax rate is the rate of the product's tax code valid on $date (so a new PPN rate row applies
     * without editing every product), and 0 when the company is not PKP (no PPN charged).
     *
     * A product with variants yields one entry per variant (title "product - variant", the variant's SKU and price)
     * instead of the product itself; in search mode only active variants, by ids all of them plus the product.
     *
     * @param array{q?: string, ids?: list<int>} $criteria q: search (active products only); ids: exact products (any status).
     * @param string                             $date     Y-m-d, decides the valid tax rate.
     * @param int                                $limit    Max products for a search.
     *
     * @return list<array{id: int, variant_id: int|null, sku: string, title: string, description: string, unit_code: string, item_type: string, unit_price: string, tax_code: string|null, tax_rate: string, is_active: bool, image_url: string|null}>
     */
    public function lineDefaults(array $criteria, string $date, int $limit = 20): array
    {
        $this->select('product_list.*, unit_of_measurement.code AS unit_code, tax_rate.code AS tax_code, tax_rate.rate AS tax_row_rate')
            ->join('unit_of_measurement', 'unit_of_measurement.id = product_list.unit_id', 'left')
            ->join('tax_rate', 'tax_rate.id = product_list.tax_rate_id', 'left');

        $byIds = isset($criteria['ids']);

        if ($byIds) {
            if ($criteria['ids'] === []) {
                return [];
            }

            $rows = $this->whereIn('product_list.id', $criteria['ids'])->findAll();
        } else {
            $this->where('product_list.is_active', 1)->orderBy('product_list.pl_title');
            $q = trim((string) ($criteria['q'] ?? ''));

            if ($q !== '') {
                $this->groupStart()
                    ->like('product_list.pl_sku', $q)
                    ->orLike('product_list.pl_title', $q)
                    ->orLike('product_list.pl_alias', $q)
                    ->orWhereIn('product_list.id', static fn ($b) => $b->select('product_id')->from('product_variant')
                        ->where('deleted_at', null)->groupStart()->like('pv_name', $q)->orLike('pv_sku', $q)->groupEnd())
                    ->groupEnd();
            }

            $rows = $this->findAll($limit);
        }

        $variants = (new ProductVariantModel())->forProducts(array_map('intval', array_column($rows, 'id')), ! $byIds);
        $profile  = (new CompanyProfileModel())->getProfile();
        $isPkp    = $profile === null || (int) $profile['is_pkp'] === 1;
        $taxes    = new TaxRateModel();
        $rates    = [];
        $result   = [];

        foreach ($rows as $row) {
            $rate = '0.00';
            $code = $row['tax_code'];

            if ($isPkp && $code !== null) {
                $rates[$code] ??= $taxes->currentFor($code, $date)['rate'] ?? $row['tax_row_rate'];
                $rate = (string) $rates[$code];
            }

            $base = [
                'id'          => (int) $row['id'],
                'variant_id'  => null,
                'sku'         => $row['pl_sku'],
                'title'       => $row['pl_title'],
                'description' => (string) ($row['pl_description'] ?? ''),
                'unit_code'   => (string) $row['unit_code'],
                'item_type'   => $row['pl_type'],
                'unit_price'  => $row['pl_price'],
                'tax_code'    => $isPkp ? $code : null,
                'tax_rate'    => $rate,
                'price_incl_tax' => (bool) ($row['pl_price_incl_tax'] ?? false),
                'min_price'   => $row['pl_min_price'] ?? null,
                'is_active'   => (bool) $row['is_active'],
                'image_url'   => $row['image_path'] ? site_url('products/' . $row['id'] . '/image') : null,
            ];
            $own = $variants[(int) $row['id']] ?? [];

            if ($own === [] || $byIds) {
                $result[] = $base;
            }

            foreach ($own as $v) {
                $result[] = [
                    'variant_id' => (int) $v['id'],
                    'sku'        => $v['pv_sku'],
                    'title'      => self::variantTitle($row['pl_title'], $v['pv_name']),
                    'unit_price' => $v['pv_price'],
                    'min_price'  => $v['pv_min_price'] ?? $base['min_price'],
                    'is_active'  => $base['is_active'] && (int) $v['is_active'] === 1,
                    'image_url'  => $v['image_path'] ? site_url('products/variants/' . $v['id'] . '/image') : $base['image_url'],
                ] + $base;
            }
        }

        return $result;
    }

    /**
     * Line title of a variant: "product - variant" (cut to the 200 characters of item_title).
     *
     * @param string $productTitle Product title.
     * @param string $variantName  Variant name.
     *
     * @return string
     */
    public static function variantTitle(string $productTitle, string $variantName): string
    {
        return mb_substr($productTitle . ' - ' . $variantName, 0, 200);
    }
}
