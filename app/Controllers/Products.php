<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\ImageUpload;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Models\TaxRateModel;
use App\Models\UnitModel;
use App\Services\ProductService;
use App\Services\ValidationFailed;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Products with picture, description and variants (permission products.view / products.manage).
 */
class Products extends BaseController
{
    private const FIELDS = ['pl_title', 'pl_alias', 'pl_description', 'pl_type', 'unit_id', 'pl_price', 'pl_price_incl_tax', 'pl_min_price', 'tax_rate_id', 'is_active'];

    private ProductModel $model;

    public function __construct()
    {
        $this->model = new ProductModel();
    }

    /**
     * Searchable, paginated list.
     *
     * @return string
     */
    public function index(): string
    {
        $search   = trim((string) $this->request->getGet('q'));
        $status   = (string) $this->request->getGet('status');
        $products = $this->model->search($search, $status)->paginate(20);

        return view('products/index', [
            'products' => $products,
            'variants' => (new ProductVariantModel())->activeCounts(array_map('intval', array_column($products, 'id'))),
            'pager'    => $this->model->pager,
            'search'   => $search,
            'status'   => $status,
        ]);
    }

    /**
     * Product page: picture, description, variants (read-only for sales).
     *
     * @param int $id Product id.
     *
     * @return string
     */
    public function show(int $id): string
    {
        $product = $this->model->search('', '')->where('product_list.id', $id)->first();

        if ($product === null) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('products/show', [
            'product'  => $product,
            'variants' => (new ProductVariantModel())->forProduct($id),
            'inUse'    => $this->model->isInUse($id),
        ]);
    }

    /**
     * Create form.
     *
     * @return string
     */
    public function new(): string
    {
        return view('products/form', ['product' => []] + $this->formOptions());
    }

    /**
     * Store a new product (SKU generated, optional picture).
     *
     * @return RedirectResponse
     */
    public function create(): RedirectResponse
    {
        try {
            $result = (new ProductService())->create($this->postFields(self::FIELDS), $this->request->getFile('image'));
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (Throwable $e) {
            return $this->failed($e, 'Product create');
        }

        return redirect()->to('products/' . $result['id'])->with('message', lang('Products.created', [$result['sku']]));
    }

    /**
     * Edit form with the variants.
     *
     * @param int $id Product id.
     *
     * @return string
     */
    public function edit(int $id): string
    {
        $product = $this->findOr404($this->model, $id);

        return view('products/form', [
            'product'  => $product,
            'inUse'    => $this->model->isInUse($id),
            'variants' => (new ProductVariantModel())->forProduct($id),
        ] + $this->formOptions());
    }

    /**
     * Update a product. Existing documents keep their copied values.
     *
     * @param int $id Product id.
     *
     * @return RedirectResponse
     */
    public function update(int $id): RedirectResponse
    {
        $product = $this->findOr404($this->model, $id);

        try {
            (new ProductService())->update($id, $this->postFields(self::FIELDS), $this->request->getFile('image'), $this->request->getPost('remove_image') === '1');
        } catch (ValidationFailed $e) {
            return $this->backWithErrors($e->errors);
        } catch (Throwable $e) {
            return $this->failed($e, 'Product update');
        }

        return redirect()->to('products/' . $id)->with('message', lang('Products.updated', [$product['pl_sku']]));
    }

    /**
     * Delete a product that no document uses (with its variants).
     *
     * @param int $id Product id.
     *
     * @return RedirectResponse
     */
    public function delete(int $id): RedirectResponse
    {
        $product = $this->findOr404($this->model, $id);

        try {
            (new ProductService())->delete($id);
        } catch (RuntimeException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Product delete');
        }

        return redirect()->to('products')->with('message', lang('Products.deleted', [$product['pl_sku']]));
    }

    /**
     * Add a variant.
     *
     * @param int $id Product id.
     *
     * @return RedirectResponse
     */
    public function createVariant(int $id): RedirectResponse
    {
        return $this->saveVariant($id, null);
    }

    /**
     * Update a variant.
     *
     * @param int $id        Product id.
     * @param int $variantId Variant id.
     *
     * @return RedirectResponse
     */
    public function updateVariant(int $id, int $variantId): RedirectResponse
    {
        return $this->saveVariant($id, $variantId);
    }

    /**
     * Delete a variant that no document uses.
     *
     * @param int $id        Product id.
     * @param int $variantId Variant id.
     *
     * @return RedirectResponse
     */
    public function deleteVariant(int $id, int $variantId): RedirectResponse
    {
        $this->findOr404($this->model, $id);

        try {
            (new ProductService())->deleteVariant($id, $variantId);
        } catch (RuntimeException $e) {
            return redirect()->to('products/' . $id . '/edit#variants')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Variant delete');
        }

        return redirect()->to('products/' . $id . '/edit#variants')->with('message', lang('Products.variantDeleted'));
    }

    /**
     * Stream the product picture.
     *
     * @param int $id Product id.
     *
     * @return ResponseInterface
     */
    public function image(int $id): ResponseInterface
    {
        $product = $this->model->withDeleted()->find($id);

        return ImageUpload::stream($this->response, $product['image_path'] ?? null, ProductService::IMAGE_FOLDER);
    }

    /**
     * Stream a variant picture.
     *
     * @param int $variantId Variant id.
     *
     * @return ResponseInterface
     */
    public function variantImage(int $variantId): ResponseInterface
    {
        $variant = (new ProductVariantModel())->withDeleted()->find($variantId);

        return ImageUpload::stream($this->response, $variant['image_path'] ?? null, ProductService::IMAGE_FOLDER);
    }

    /**
     * Create or update a variant from the posted form.
     *
     * @param int      $id        Product id.
     * @param int|null $variantId Variant id (null = new).
     *
     * @return RedirectResponse
     */
    private function saveVariant(int $id, ?int $variantId): RedirectResponse
    {
        $this->findOr404($this->model, $id);
        $data = $this->postFields(['pv_name', 'pv_price', 'pv_min_price', 'is_active', 'sort_order']);

        try {
            (new ProductService())->saveVariant($id, $variantId, $data, $this->request->getFile('variant_image'), $this->request->getPost('remove_image') === '1');
        } catch (ValidationFailed $e) {
            return redirect()->to('products/' . $id . '/edit#variants')->withInput()
                ->with('errors', $e->errors)->with('variant_form', $variantId ?? 'new');
        } catch (RuntimeException $e) {
            return redirect()->to('products/' . $id . '/edit#variants')->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return $this->failed($e, 'Variant save');
        }

        return redirect()->to('products/' . $id . '/edit#variants')->with('message', lang($variantId === null ? 'Products.variantCreated' : 'Products.variantUpdated'));
    }

    /**
     * Select options for units and tax rates.
     *
     * @return array{units: array<int, string>, taxRates: array<int, string>}
     */
    private function formOptions(): array
    {
        $units = [];

        foreach ((new UnitModel())->orderBy('code')->findAll() as $u) {
            $units[(int) $u['id']] = $u['code'] . ' - ' . $u['name'];
        }

        return ['units' => $units, 'taxRates' => (new TaxRateModel())->options()];
    }
}
