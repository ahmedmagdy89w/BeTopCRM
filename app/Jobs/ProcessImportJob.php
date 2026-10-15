<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\Import;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\CompanySettingsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class ProcessImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(private Import $import, private string $storedPath) {}

    public function handle(): void
    {
        $this->import->update(['status' => 'processing']);

        $errors = [];
        $success = 0;
        $failed = 0;
        $row = 0;

        try {
            $stream = Storage::disk('local')->readStream($this->storedPath);
            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to read the import file.');
            }

            $headers = fgetcsv($stream, null, ',', '"', '');
            if (! $headers) {
                $this->failImport('CSV file must include a header row.');

                return;
            }

            $headers = array_map(static fn ($header) => trim((string) $header), $headers);
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            if (in_array('', $headers, true) || count(array_unique($headers)) !== count($headers)) {
                $this->failImport('CSV headers must be non-empty and unique.');

                return;
            }

            $total = 0;
            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                if (! $this->isEmptyRow($values)) {
                    $total++;
                }
            }
            rewind($stream);
            fgetcsv($stream, null, ',', '"', '');
            $this->import->update(['total_rows' => $total]);

            while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
                if ($this->isEmptyRow($values)) {
                    continue;
                }

                $row++;
                if (count($values) !== count($headers)) {
                    $failed++;
                    $errors[] = [
                        'row' => $row,
                        'field' => 'row',
                        'message' => 'Column count does not match the CSV header.',
                    ];

                    continue;
                }

                $data = array_combine($headers, array_map(
                    static fn ($value) => $value === '' ? null : $value,
                    $values,
                ));

                $result = $this->processRow($row, $data);

                if ($result === true) {
                    $success++;
                } else {
                    $failed++;
                    $errors = array_merge($errors, $result);
                }

                if ($row % 50 === 0) {
                    $this->import->update(['processed_rows' => $row]);
                }
            }

            $this->import->update([
                'status' => 'completed',
                'processed_rows' => $row,
                'successful_rows' => $success,
                'failed_rows' => $failed,
                'errors' => $errors ?: null,
                'completed_at' => now(),
            ]);
        } finally {
            if (isset($stream) && is_resource($stream)) {
                fclose($stream);
            }
            Storage::disk('local')->delete($this->storedPath);
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->import->update([
            'status' => 'failed',
            'errors' => [['row' => 0, 'field' => 'file', 'message' => $e->getMessage()]],
        ]);
    }

    private function processRow(int $rowNum, array $data): bool|array
    {
        $errors = [];

        match ($this->import->entity_type) {
            'leads', 'customers' => $errors = $this->importCustomer($rowNum, $data),
            'opportunities' => $errors = $this->importOpportunity($rowNum, $data),
            'products' => $errors = $this->importProduct($rowNum, $data),
            'categories' => $errors = $this->importCategory($rowNum, $data),
            default => $errors = [['row' => $rowNum, 'field' => 'entity_type', 'message' => 'Unsupported entity type.']],
        };

        return empty($errors) ? true : $errors;
    }

    private function importCustomer(int $row, array $data): array
    {
        $errors = [];

        $isLead = $this->import->entity_type === 'leads';
        $type = $data['type'] ?? 'individual';

        $validator = Validator::make($data, [
            'type' => ['nullable', 'in:individual,company'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'industry' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(Customer::SOURCES)],
            'lead_status' => ['nullable', Rule::in(Customer::LEAD_STATUSES)],
            'status' => ['nullable', Rule::in($isLead ? Customer::LEAD_STATUSES : Customer::CUSTOMER_STATUSES)],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrors($validator, $row);
        }

        if ($type === 'company' && empty($data['company_name'])) {
            return [['row' => $row, 'field' => 'company_name', 'message' => 'Company name is required for company type.']];
        }
        if ($type === 'individual' && (empty($data['first_name']) || empty($data['last_name']))) {
            return [['row' => $row, 'field' => 'first_name', 'message' => 'First and last name are required for individual type.']];
        }

        if (! empty($data['email']) && Customer::where('email', $data['email'])->exists()) {
            return [['row' => $row, 'field' => 'email', 'message' => 'Customer with this email already exists.']];
        }

        Customer::create([
            'type' => $type,
            'is_lead' => $isLead,
            'lead_status' => $isLead ? ($data['lead_status'] ?? $data['status'] ?? 'new') : null,
            'status' => $isLead ? 'active' : ($data['status'] ?? 'active'),
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'industry' => $data['industry'] ?? null,
            'website' => $data['website'] ?? null,
            'source' => $data['source'] ?? 'other',
            'estimated_value' => $data['estimated_value'] ?? null,
            'contract_value' => $data['contract_value'] ?? null,
            'description' => $data['description'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $this->import->user_id,
        ]);

        return [];
    }

    private function importOpportunity(int $row, array $data): array
    {
        $validator = Validator::make($data, [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrors($validator, $row);
        }

        Opportunity::create([
            'customer_id' => $data['customer_id'],
            'title' => $data['title'],
            'stage' => $data['stage'] ?? 'prospecting',
            'amount' => $data['amount'] ?? null,
            'expected_close_date' => $data['expected_close_date'] ?? null,
            'created_by' => $this->import->user_id,
        ]);

        return [];
    }

    private function importProduct(int $row, array $data): array
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'category_slug' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrors($validator, $row);
        }

        $sku = trim((string) ($data['sku'] ?? ''));
        if ($sku !== '' && Product::query()->where('sku', $sku)->exists()) {
            return [['row' => $row, 'field' => 'sku', 'message' => 'Product with this SKU already exists.']];
        }

        $category = null;
        $categorySlug = trim((string) ($data['category_slug'] ?? ''));
        if ($categorySlug !== '') {
            $category = ProductCategory::query()->where('slug', $categorySlug)->first();
            if (! $category) {
                return [['row' => $row, 'field' => 'category_slug', 'message' => 'Category with this slug does not exist.']];
            }
        }

        $isActive = ! isset($data['is_active']) || $data['is_active'] === ''
        ? true
        : filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);

        Product::create([
            'name' => $data['name'],
            'sku' => $sku !== '' ? $sku : null,
            'description' => $data['description'] ?? null,
            'unit_price' => $data['unit_price'],
            'currency' => CompanySettingsService::defaultCurrency(),
            'category_id' => $category?->id,
            'is_active' => $isActive,
            'created_by' => $this->import->user_id,
        ]);

        return [];
    }

    private function importCategory(int $row, array $data): array
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:255'],
            'parent_slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->validationErrors($validator, $row);
        }

        $slug = trim((string) ($data['slug'] ?? '')) ?: str($data['name'])->slug()->toString();
        if (ProductCategory::query()->where('name', $data['name'])->orWhere('slug', $slug)->exists()) {
            return [['row' => $row, 'field' => 'name', 'message' => 'Category with this name or slug already exists.']];
        }

        $parent = null;
        $parentSlug = trim((string) ($data['parent_slug'] ?? ''));
        if ($parentSlug !== '') {
            $parent = ProductCategory::query()->where('slug', $parentSlug)->first();
            if (! $parent) {
                return [['row' => $row, 'field' => 'parent_slug', 'message' => 'Parent category with this slug does not exist.']];
            }
        }

        ProductCategory::create([
            'name' => $data['name'],
            'slug' => $slug,
            'parent_id' => $parent?->id,
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_by' => $this->import->user_id,
        ]);

        return [];
    }

    private function validationErrors(\Illuminate\Validation\Validator $validator, int $row): array
    {
        $errors = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            $errors[] = ['row' => $row, 'field' => $field, 'message' => $messages[0]];
        }

        return $errors;
    }

    private function isEmptyRow(array $values): bool
    {
        return count($values) === 1 && ($values[0] === null || trim((string) $values[0]) === '');
    }

    private function failImport(string $message): void
    {
        $this->import->update([
            'status' => 'failed',
            'total_rows' => 0,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 1,
            'errors' => [['row' => 0, 'field' => 'header', 'message' => $message]],
            'completed_at' => now(),
        ]);
    }
}
