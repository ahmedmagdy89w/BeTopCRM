<?php

use App\Jobs\ProcessImportJob;
use App\Models\Customer;
use App\Models\Import;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ── Import ───────────────────────────────────────────────────────────────────

test('admin can upload CSV import for leads', function () {
    Storage::fake('local');

    $admin = adminUser();
    $csvData = "first_name,last_name,email,source,status\nJohn,Doe,john@import.com,web,new\nJane,Smith,jane@import.com,web,new";
    $file = UploadedFile::fake()->createWithContent('leads.csv', $csvData);

    $this->actingAs($admin)
        ->postJson('/api/v1/import/leads', ['file' => $file])
        ->assertStatus(201);

    expect(Customer::query()->where('email', 'john@import.com')->firstOrFail()->is_lead)->toBeTrue();
});

test('admin can check import status', function () {
    Storage::fake('local');

    $admin = adminUser();
    $csvData = "first_name,last_name,email\nBob,Jones,bob@test.com";
    $file = UploadedFile::fake()->createWithContent('leads.csv', $csvData);

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/import/leads', ['file' => $file])
        ->assertStatus(201);

    $importId = $response->json('data.id');

    $this->actingAs($admin)
        ->getJson("/api/v1/import/{$importId}")
        ->assertOk()
        ->assertJsonPath('data.entity_type', 'leads');
});

test('admin can download import template for leads', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/import/customers/template')
        ->assertOk();
});

test('product and category templates and upload routes are available', function () {
    Storage::fake('local');

    $admin = adminUser();
    $productTemplate = $this->actingAs($admin)->get('/api/v1/import/products/template')->assertOk()->streamedContent();
    $categoryTemplate = $this->get('/api/v1/import/categories/template')->assertOk()->streamedContent();

    expect($productTemplate)->toContain('category_slug')
        ->and($categoryTemplate)->toContain('parent_slug');

    $file = UploadedFile::fake()->createWithContent(
        'products.csv',
        "name,sku,description,unit_price,currency,category_slug,is_active\nAPI Product,API-001,Uploaded,12.50,USD,,1",
    );
    $this->postJson('/api/v1/import/products', ['file' => $file])->assertCreated();

    expect(Product::query()->where('sku', 'API-001')->exists())->toBeTrue();
});

test('admin can list imports', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->getJson('/api/v1/import')
        ->assertOk();
});

test('customer CSV import creates customers rather than leads by default', function () {
    Storage::fake('local');

    $admin = adminUser();
    $path = 'imports/customers.csv';
    Storage::disk('local')->put($path, "type,first_name,last_name,email,source,status,description\nindividual,Jamie,Customer,jamie.customer@example.com,,,\"First line,\nsecond line\"");

    $import = Import::create([
        'user_id' => $admin->id,
        'entity_type' => 'customers',
        'original_filename' => 'customers.csv',
        'status' => 'pending',
    ]);

    (new ProcessImportJob($import, $path))->handle();

    $customer = Customer::query()->where('email', 'jamie.customer@example.com')->firstOrFail();
    expect($customer->is_lead)->toBeFalse()
        ->and($customer->description)->toBe("First line,\nsecond line");

    $customerCsv = $this->actingAs($admin)->get('/api/v1/export/customers')->assertOk()->streamedContent();
    expect($customerCsv)->toContain(
        'type,first_name,last_name,email,phone,company_name,industry,website,source,status,contract_value,description,notes',
        'jamie.customer@example.com',
    );
});

test('catalog CSV import resolves category hierarchy and product category slugs', function () {
    Storage::fake('local');

    $admin = adminUser();
    $categoryPath = 'imports/categories.csv';
    Storage::disk('local')->put($categoryPath, "name,slug,parent_slug,description,sort_order\nHardware,hardware,,Hardware items,0\nMonitors,monitors,hardware,Display panels,1");

    $categoryImport = Import::create([
        'user_id' => $admin->id,
        'entity_type' => 'categories',
        'original_filename' => 'categories.csv',
        'status' => 'pending',
    ]);
    (new ProcessImportJob($categoryImport, $categoryPath))->handle();

    $productPath = 'imports/products.csv';
    Storage::disk('local')->put($productPath, "name,sku,description,unit_price,currency,category_slug,is_active\nDisplay,DSP-001,Panel,199.99,USD,monitors,1");

    $productImport = Import::create([
        'user_id' => $admin->id,
        'entity_type' => 'products',
        'original_filename' => 'products.csv',
        'status' => 'pending',
    ]);
    (new ProcessImportJob($productImport, $productPath))->handle();

    $monitor = ProductCategory::query()->where('slug', 'monitors')->firstOrFail();
    expect($monitor->parent->slug)->toBe('hardware')
        ->and(Product::query()->where('sku', 'DSP-001')->firstOrFail()->category_id)->toBe($monitor->id)
        ->and($categoryImport->fresh()->successful_rows)->toBe(2)
        ->and($productImport->fresh()->successful_rows)->toBe(1);
});

test('CSV import skips duplicate products and reports malformed rows', function () {
    Storage::fake('local');

    $admin = adminUser();
    Product::factory()->create(['created_by' => $admin->id, 'sku' => 'DUP-001']);
    $path = 'imports/products-with-errors.csv';
    Storage::disk('local')->put($path, "name,sku,description,unit_price,currency,category_slug,is_active\nGood,GOOD-001,Valid,15.00,USD,,1\nDuplicate,DUP-001,Already exists,20.00,USD,,1\nMissing Category,MISSING-001,Invalid category,25.00,USD,missing-category,1\nMalformed,SHORT");

    $import = Import::create([
        'user_id' => $admin->id,
        'entity_type' => 'products',
        'original_filename' => 'products-with-errors.csv',
        'status' => 'pending',
    ]);

    (new ProcessImportJob($import, $path))->handle();

    $completedImport = $import->fresh();
    expect($completedImport->total_rows)->toBe(4)
        ->and($completedImport->successful_rows)->toBe(1)
        ->and($completedImport->failed_rows)->toBe(3)
        ->and($completedImport->errors)->toHaveCount(3)
        ->and(collect($completedImport->errors)->pluck('field')->all())->toContain('category_slug')
        ->and(Product::query()->where('sku', 'GOOD-001')->exists())->toBeTrue()
        ->and(Product::query()->where('name', 'Duplicate')->exists())->toBeFalse();
});

test('category import reports duplicate categories and unresolved parents', function () {
    Storage::fake('local');

    $admin = adminUser();
    ProductCategory::factory()->create([
        'created_by' => $admin->id,
        'name' => 'Hardware',
        'slug' => 'hardware',
    ]);
    $path = 'imports/categories-with-errors.csv';
    Storage::disk('local')->put($path, "name,slug,parent_slug,description,sort_order\nHardware,hardware,,Duplicate,0\nDisplays,displays,missing-parent,Panels,1");

    $import = Import::create([
        'user_id' => $admin->id,
        'entity_type' => 'categories',
        'original_filename' => 'categories-with-errors.csv',
        'status' => 'pending',
    ]);

    (new ProcessImportJob($import, $path))->handle();

    expect($import->fresh()->failed_rows)->toBe(2)
        ->and(collect($import->fresh()->errors)->pluck('field')->all())->toBe(['name', 'parent_slug'])
        ->and(ProductCategory::query()->count())->toBe(1);
});

test('admin can export products and categories as portable CSV', function () {
    $admin = adminUser();
    $root = ProductCategory::factory()->create([
        'created_by' => $admin->id,
        'name' => 'Hardware',
        'slug' => 'hardware',
        'parent_id' => null,
    ]);
    $child = ProductCategory::factory()->create([
        'created_by' => $admin->id,
        'name' => 'Monitors',
        'slug' => 'monitors',
        'parent_id' => $root->id,
    ]);
    Product::factory()->inactive()->create([
        'created_by' => $admin->id,
        'name' => 'Display',
        'sku' => 'DSP-001',
        'category_id' => $child->id,
    ]);

    $productCsv = $this->actingAs($admin)->get('/api/v1/export/products')->assertOk()->streamedContent();
    $categoryCsv = $this->get('/api/v1/export/categories')->assertOk()->streamedContent();

    $categoryRows = array_map('str_getcsv', explode("\n", trim($categoryCsv)));
    expect($productCsv)->toContain('category_slug', 'monitors', 'DSP-001', ',0')
        ->and($categoryRows[1][1])->toBe('hardware')
        ->and($categoryRows[2][1])->toBe('monitors')
        ->and($categoryRows[2][2])->toBe('hardware');
});

// ── Export ───────────────────────────────────────────────────────────────────

test('admin can export leads as CSV', function () {
    $admin = adminUser();
    Customer::factory(3)->create(['created_by' => $admin->id]);

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/export/leads')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});

test('admin can export opportunities as CSV', function () {
    $admin = adminUser();
    Customer::factory(2)->create(['created_by' => $admin->id]);

    $this->actingAs($admin)
        ->getJson('/api/v1/export/opportunities')
        ->assertOk();
});

// ── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated request on imports returns 401', function () {
    $this->getJson('/api/v1/import')->assertUnauthorized();
});

test('sales_rep cannot import data', function () {
    Storage::fake('local');
    $rep = salesRepUser();
    $csvData = "first_name,last_name,email\nBob,Jones,bob@test.com";
    $file = UploadedFile::fake()->createWithContent('leads.csv', $csvData);

    $this->actingAs($rep)
        ->postJson('/api/v1/import/leads', ['file' => $file])
        ->assertForbidden();
});

// ── Validation ───────────────────────────────────────────────────────────────

test('import requires a file', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->postJson('/api/v1/import/leads', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});
