<?php

namespace App\Services;

use App\Filters\CustomerFilter;
use App\Filters\ProductFilter;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function leads(array $filters, User $user): StreamedResponse
    {
        $columns = ['type', 'first_name', 'last_name', 'email', 'phone', 'company_name', 'industry', 'website', 'source', 'lead_status', 'estimated_value', 'description', 'notes'];

        return $this->stream('leads', $columns, function () use ($filters, $user) {
            $query = Customer::query()->where('is_lead', true);
            if ($user->hasRole('sales_rep')) {
                $query->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id));
            }

            return (new CustomerFilter(array_merge($filters, ['is_lead' => true])))->apply($query)->cursor();
        });
    }

    public function customers(array $filters, User $user): StreamedResponse
    {
        $columns = ['type', 'first_name', 'last_name', 'email', 'phone', 'company_name', 'industry', 'website', 'source', 'status', 'contract_value', 'description', 'notes'];

        return $this->stream('customers', $columns, function () use ($filters, $user) {
            $query = Customer::query()->where('is_lead', false);
            if ($user->hasRole('sales_rep')) {
                $query->where('account_manager_id', $user->id);
            }

            return (new CustomerFilter(array_merge($filters, ['is_lead' => false])))->apply($query)->cursor();
        });
    }

    public function opportunities(array $filters, User $user): StreamedResponse
    {
        $columns = ['id', 'customer_id', 'title', 'stage', 'amount', 'expected_close_date', 'created_at'];

        return $this->stream('opportunities', $columns, function () {
            return Opportunity::query()->cursor();
        });
    }

    public function products(array $filters, User $user): StreamedResponse
    {
        $columns = ['name', 'sku', 'description', 'unit_price', 'currency', 'category_slug', 'is_active'];

        return $this->stream('products', $columns, function () use ($filters) {
            return (new ProductFilter($filters, true))
                ->apply(Product::query()->with('category'))
                ->lazy();
        });
    }

    public function categories(array $filters, User $user): StreamedResponse
    {
        $columns = ['name', 'slug', 'parent_slug', 'description', 'sort_order'];

        return $this->stream('categories', $columns, fn () => $this->categoryTree());
    }

    private function categoryTree(): \Generator
    {
        $parentIds = null;

        do {
            $nextParentIds = [];
            $parentIdBatches = $parentIds === null ? [null] : array_chunk($parentIds, 500);

            foreach ($parentIdBatches as $parentIdBatch) {
                $query = ProductCategory::query()
                    ->leftJoin('product_categories as parents', 'product_categories.parent_id', '=', 'parents.id')
                    ->select('product_categories.*', 'parents.slug as parent_slug')
                    ->orderBy('product_categories.sort_order')
                    ->orderBy('product_categories.id');

                if ($parentIdBatch === null) {
                    $query->whereNull('product_categories.parent_id');
                } else {
                    $query->whereIn('product_categories.parent_id', $parentIdBatch);
                }

                foreach ($query->cursor() as $category) {
                    $nextParentIds[] = $category->id;
                    yield $category;
                }
            }

            $parentIds = $nextParentIds;
        } while ($parentIds !== []);
    }

    private function stream(string $name, array $columns, callable $queryFn): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $queryFn) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            foreach ($queryFn() as $record) {
                $values = array_map(function ($column) use ($record) {
                    $value = match ($column) {
                        'category_slug' => $record instanceof Product ? $record->category?->slug : null,
                        default => $record->$column ?? null,
                    };

                    if (is_bool($value)) {
                        return $value ? '1' : '0';
                    }

                    return is_array($value) ? json_encode($value) : ($value ?? '');
                }, $columns);

                fputcsv($out, $values);
            }

            fclose($out);
        }, "{$name}-export.csv", [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$name}-export.csv\"",
        ]);
    }
}
