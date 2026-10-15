<?php

use App\Exports\ProductPerformanceReportExport;
use App\Models\CompanySetting;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\CompanySettingsService;

// ── PUT /api/v1/company/settings (product labels) ─────────────────────────────

test('admin can update product labels and read them back', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', [
            'product_labels' => [
                'singular_en' => 'Service',
                'plural_en' => 'Services',
                'singular_ar' => 'خدمة',
                'plural_ar' => 'خدمات',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.product_labels.singular_en', 'Service')
        ->assertJsonPath('data.product_labels.plural_en', 'Services')
        ->assertJsonPath('data.product_labels.singular_ar', 'خدمة')
        ->assertJsonPath('data.product_labels.plural_ar', 'خدمات');

    expect(CompanySetting::getValue('product_plural_en'))->toBe('Services');
});

test('product labels default to null when unset', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/company/settings')
        ->assertOk()
        ->assertJsonPath('data.product_labels.singular_en', null)
        ->assertJsonPath('data.product_labels.plural_en', null)
        ->assertJsonPath('data.product_labels.singular_ar', null)
        ->assertJsonPath('data.product_labels.plural_ar', null);
});

test('product labels fail validation when too long', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', [
            'product_labels' => ['plural_en' => str_repeat('a', 61)],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product_labels.plural_en']);
});

// ── Cache: fast reads, instant invalidation ───────────────────────────────────

test('settings cache invalidates on update', function () {
    $admin = adminUser();

    // Prime the cache.
    expect(CompanySetting::cachedAll())->toBeArray();

    $this->actingAs($admin)
        ->putJson('/api/v1/company/settings', [
            'product_labels' => ['plural_en' => 'Services'],
        ])
        ->assertOk();

    expect(CompanySetting::cachedAll()['product_plural_en'])->toBe('Services');
    expect(CompanySettingsService::productLabels('en', 'Product', 'Products')['plural'])->toBe('Services');
});

test('product labels fall back to defaults when unset', function () {
    expect(CompanySettingsService::productLabels('en', 'Product', 'Products'))
        ->toBe(['singular' => 'Product', 'plural' => 'Products']);
});

// ── Label-aware exports ───────────────────────────────────────────────────────

test('product performance export uses custom labels', function () {
    CompanySetting::setMany([
        'product_singular_en' => 'Service',
        'product_plural_en' => 'Services',
    ]);

    $export = new ProductPerformanceReportExport(['data' => []]);

    expect($export->headings()[0])->toBe('Service')
        ->and($export->title())->toBe('Services Performance Report');
});

test('product performance export falls back to built-in strings', function () {
    $export = new ProductPerformanceReportExport(['data' => []]);

    expect($export->headings()[0])->toBe(__('reports.product'))
        ->and($export->title())->toBe(__('reports.product_performance_title'));
});

test('category delete message uses custom labels', function () {
    $admin = adminUser();
    $category = ProductCategory::factory()->create(['created_by' => $admin->id]);
    Product::factory()->create(['created_by' => $admin->id, 'category_id' => $category->id, 'is_active' => true]);
    CompanySetting::setMany(['product_plural_en' => 'Services']);

    $this->actingAs($admin)
        ->withHeader('Accept-Language', 'en')
        ->deleteJson("/api/v1/product-categories/{$category->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.category.0', 'Cannot delete a category that has Services or sub-categories assigned to it.');
});
