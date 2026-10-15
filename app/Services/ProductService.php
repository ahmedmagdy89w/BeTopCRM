<?php

namespace App\Services;

use App\Filters\ProductFilter;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(
        private CustomFieldService $customFields,
        private CustomFieldEntityRegistry $registry,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        $query = Product::with(['category', 'tax']);

        $query = (new ProductFilter($filters))->apply($query);

        return $query->paginate(min((int) ($filters['per_page'] ?? 15), 100));
    }

    public function find(int $productId): Product
    {
        return Product::query()
            ->with(['category', 'tax', 'creator'])
            ->findOrFail($productId);
    }

    /**
     * @return Collection<int, Opportunity>
     */
    public function listOpportunities(Product $product): Collection
    {
        return $product->opportunities()
            ->with(['customer', 'assignee', 'creator'])
            ->orderByDesc('opportunities.created_at')
            ->get();
    }

    public function create(array $data, User $user): Product
    {
        $data['created_by'] = $user->id;
        // Currency is settings-owned: always the company default.
        $data['currency'] = CompanySettingsService::defaultCurrency();

        $this->ensureActiveTax($data['tax_id'] ?? null);

        $categoryId = isset($data['category_id']) && $data['category_id'] !== null
            ? (int) $data['category_id']
            : null;

        $data = $this->reconcileCategoryValues($data, $categoryId, [], true);

        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $data = $this->reconcileCategoryValues($data, $product->category_id, $product->category_custom_fields ?? []);

        if (array_key_exists('tax_id', $data)) {
            $this->ensureActiveTax($data['tax_id']);
        }

        // Currency is settings-owned: never taken from request input.
        unset($data['currency']);

        $product->update($data);

        return $product->fresh(['category', 'tax']);
    }

    public function delete(Product $product): void
    {
        // Soft toggle: deactivate rather than delete
        $product->update(['is_active' => false]);
    }

    /**
     * Inactive taxes cannot be attached to products. Existing snapshots
     * on quotations/invoices keep working.
     */
    private function ensureActiveTax(mixed $taxId): void
    {
        if ($taxId === null || $taxId === '') {
            return;
        }

        $tax = Tax::find($taxId);

        if (! $tax || ! $tax->is_active) {
            throw ValidationException::withMessages([
                'tax_id' => ['The selected tax is not active.'],
            ]);
        }
    }

    /**
     * The authoritative write path for category-scoped values, so a client cannot
     * smuggle in keys the target category never declared.
     *
     * Three cases, which is why this is not a one-liner:
     *  - create: reconcile the payload against the category it is being created in.
     *  - category changed: keep stored values whose names survive in the new
     *    category, drop the rest, then merge and filter the incoming values.
     *  - category unchanged: do not touch stored values, so a partial update that
     *    omits them does not silently wipe them. Only submitted keys are filtered.
     */
    private function reconcileCategoryValues(
        array $data,
        ?int $currentCategoryId,
        array $storedValues = [],
        bool $isCreate = false,
    ): array {
        $column = $this->registry->categoryValuesColumn('product');
        $entityType = 'product';

        $categorySubmitted = array_key_exists('category_id', $data);
        $targetCategoryId = $categorySubmitted
            ? ($data['category_id'] !== null ? (int) $data['category_id'] : null)
            : $currentCategoryId;

        $categoryChanged = $targetCategoryId !== $currentCategoryId;

        $incoming = $data[$column] ?? null;
        $hasIncoming = is_array($incoming);

        if ($isCreate) {
            $data[$column] = $this->normalise($this->customFields->reconcileCategoryValues(
                $hasIncoming ? $incoming : [],
                $targetCategoryId,
                $entityType,
            ));

            return $data;
        }

        if ($categoryChanged) {
            $retained = $this->customFields->reconcileCategoryValues(
                $storedValues,
                $targetCategoryId,
                $entityType,
            );

            $data[$column] = $this->normalise($this->customFields->reconcileCategoryValues(
                [...$retained, ...($hasIncoming ? $incoming : [])],
                $targetCategoryId,
                $entityType,
            ));

            return $data;
        }

        if ($hasIncoming) {
            $data[$column] = $this->normalise($this->customFields->reconcileCategoryValues(
                $incoming,
                $targetCategoryId,
                $entityType,
            ));
        }

        return $data;
    }

    /**
     * The column is a nullable JSON *map*, so an empty result is stored as NULL
     * rather than `[]`, which would encode as a JSON array instead of an object.
     */
    private function normalise(array $values): ?array
    {
        return $values === [] ? null : $values;
    }
}
