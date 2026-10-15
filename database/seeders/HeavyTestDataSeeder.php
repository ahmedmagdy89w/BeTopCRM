<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Heavy MENA real-estate test dataset spread over the past 3 years.
 *
 * Standalone opt-in seeder — DatabaseSeeder is untouched. Run with:
 *
 *   php artisan db:seed --class=HeavyTestDataSeeder
 *
 * Set HEAVY_SEED_SCALE=small for a fast smoke-test slice (used by tests).
 */
class HeavyTestDataSeeder extends Seeder
{
    use WithoutModelEvents;

    const CURRENCY = 'EGP';

    const COMPANY = [
        'company_name' => 'Noor Estates',
        'company_email' => 'info@noor-estates.com',
        'company_phone' => '+20227350000',
        'company_website' => 'https://noor-estates.com',
        'company_tax_number' => 'EG-475-291-336',
        'company_currency' => self::CURRENCY,
        'company_timezone' => 'Africa/Cairo',
        'address_street' => '90 Road 9, Maadi',
        'address_city' => 'Cairo',
        'address_state' => 'Cairo',
        'address_country' => 'EG',
        'address_zip' => '11728',
    ];

    /** Project catalogue: name => [developer, city, category, min_price, max_price]. */
    const PROJECTS = [
        'Madinaty' => ['Talaat Moustafa Group', 'New Cairo', 'Apartments', 3000000, 8000000],
        'Al Rehab' => ['Talaat Moustafa Group', 'New Cairo', 'Apartments', 2500000, 6000000],
        'Mountain View Hyde Park' => ['Mountain View', 'New Cairo', 'Villas', 8000000, 20000000],
        'Palm Hills New Cairo' => ['Palm Hills', 'New Cairo', 'Villas', 9000000, 22000000],
        'Cairo Festival City' => ['Al-Futtaim', 'New Cairo', 'Apartments', 5000000, 12000000],
        'Marassi' => ['Emaar Misr', 'North Coast', 'Chalets', 10000000, 25000000],
        'Hacienda Bay' => ['Palm Hills', 'North Coast', 'Chalets', 12000000, 28000000],
        'New Capital Central' => ['Administrative Capital', 'New Capital', 'Offices', 4000000, 15000000],
        'Dubai Hills Estate' => ['Emaar', 'Dubai', 'Villas', 15000000, 40000000],
        'Yas Island' => ['Aldar', 'Abu Dhabi', 'Apartments', 8000000, 20000000],
        'Al Yasmin Villas' => ['Retal', 'Riyadh', 'Villas', 6000000, 16000000],
        'Zayed Retail Hub' => ['Noor Estates', 'Giza', 'Retail', 3000000, 12000000],
    ];

    const PORTALS = ['Bayut', 'Property Finder', 'Aqarmap', 'Website', 'Referral', 'WhatsApp'];

    const CITIES = [
        ['Cairo', 'Cairo', 'EG'], ['Giza', 'Giza', 'EG'], ['New Cairo', 'Cairo', 'EG'],
        ['North Coast', 'Matrouh', 'EG'], ['Alexandria', 'Alexandria', 'EG'], ['New Capital', 'Cairo', 'EG'],
        ['Dubai', 'Dubai', 'AE'], ['Abu Dhabi', 'Abu Dhabi', 'AE'],
        ['Riyadh', 'Riyadh', 'SA'], ['Jeddah', 'Makkah', 'SA'], ['Doha', 'Doha', 'QA'],
    ];

    const AR_FIRST = ['Ahmed', 'Mohamed', 'Sara', 'Fatma', 'Omar', 'Layla', 'Hassan', 'Nour', 'Khaled', 'Mona', 'Youssef', 'Heba', 'Karim', 'Dina', 'Tarek', 'Rania', 'Amr', 'Salma', 'Hossam', 'Nadia'];

    const AR_LAST = ['Hassan', 'Ibrahim', 'El-Sayed', 'Mahmoud', 'Ali', 'Moustafa', 'Khalil', 'Farouk', 'Nasser', 'Abdelaziz', 'El-Masry', 'Fahmy', 'Rashad', 'Osman', 'Hegazy'];

    const EN_FIRST = ['James', 'Emily', 'Michael', 'Sophia', 'Daniel', 'Olivia', 'Adam', 'Lina', 'Chris', 'Maya'];

    const EN_LAST = ['Smith', 'Johnson', 'Brown', 'Taylor', 'Wilson', 'Haddad', 'Khoury', 'Mansour', 'Aziz', 'Nader'];

    const CHANNEL_WEIGHTS = ['whatsapp' => 50, 'call' => 25, 'meeting' => 15, 'email' => 10];

    const STAGE_WEIGHTS = ['prospecting' => 20, 'qualification' => 20, 'proposal' => 20, 'negotiation' => 15, 'won' => 15, 'lost' => 10];

    const LEAD_STATUS_WEIGHTS = ['new' => 25, 'contacted' => 25, 'qualified' => 20, 'proposal' => 12, 'negotiation' => 10, 'lost' => 8];

    const SOURCE_WEIGHTS = ['website' => 20, 'referral' => 20, 'social_media' => 20, 'advertisement' => 15, 'cold_call' => 10, 'trade_show' => 5, 'web' => 5, 'other' => 5];

    public function run(): void
    {
        mt_srand(20260930);
        fake()->seed(20260930);

        // MySQL TIMESTAMP columns are interpreted in the session time zone.
        // Egypt (Africa/Cairo) springs forward at midnight on the last Friday
        // of April, so generated times like 2026-04-24 00:20:10 do not exist
        // there and MySQL rejects them (error 1292). Pin the session to UTC —
        // which has no DST gaps — for the whole seeding run.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("SET time_zone = '+00:00'");
        }

        // The seeder is not idempotent (deterministic emails/SKUs/numbers).
        // Abort early on re-runs instead of dying halfway on unique keys.
        if (CompanySetting::getValue('heavy_seed_batch') !== null && env('HEAVY_SEED_FORCE') !== '1' && env('HEAVY_SEED_FORCE') !== 1) {
            throw new RuntimeException(
                'HeavyTestDataSeeder already ran (heavy_seed_batch is set). '.
                'Use `php artisan migrate:fresh --seed` first, or re-run with HEAVY_SEED_FORCE=1.'
            );
        }

        $small = env('HEAVY_SEED_SCALE') === 'small';
        $customerTotal = $small ? 120 : 5000;
        $activitiesPerCustomer = $small ? 3 : 5;
        $activitiesPerOpp = $small ? 2 : 3;
        $campaignCount = $small ? 2 : 24;
        $recipientsPerCampaign = $small ? 20 : 500;
        $taskTotal = $small ? 60 : 5000;
        $noteTotal = $small ? 80 : 8000;
        $attachmentTotal = $small ? 30 : 3000;

        $this->call(RolesAndPermissionsSeeder::class);

        $userIds = $this->seedUsers();
        $adminId = User::where('email', env('ADMIN_EMAIL', 'admin@example.com'))->value('id') ?? $userIds[0];
        $this->seedCompanySettings();
        $categoryIds = $this->seedCategories($adminId);
        $this->seedCustomFields($adminId);
        $products = $this->seedProducts($adminId, $categoryIds, $small ? 20 : 100);
        $tagIds = $this->seedTags($adminId);
        $templateIds = $this->seedTemplates($adminId);

        $customers = $this->seedCustomers($userIds, $customerTotal);
        $opportunities = $this->seedOpportunities($customers, $userIds, $products);
        $this->seedOpportunityProducts($opportunities, $products);
        $this->seedCustomerActivities($customers, $userIds, $activitiesPerCustomer);
        $this->seedOpportunityActivities($opportunities, $userIds, $activitiesPerOpp);
        $quotations = $this->seedQuotations($opportunities, $userIds, $products);
        $this->seedInvoices($opportunities, $quotations, $userIds);
        $this->seedTasks($customers, $opportunities, $userIds, $taskTotal);
        $this->seedNotes($customers, $opportunities, $userIds, $noteTotal);
        $this->seedAttachments($customers, $opportunities, $userIds, $attachmentTotal);
        $this->seedTaggables($customers, $tagIds, $opportunities);
        $this->seedCampaigns($customers, $templateIds, $adminId, $campaignCount, $recipientsPerCampaign);
        $this->seedOpsFootprint($adminId);

        CompanySetting::setValue('heavy_seed_batch', $small ? 'small:'.now()->toDateTimeString() : 'full:'.now()->toDateTimeString());

        if ($this->command) {
            $this->command->info("HeavyTestDataSeeder done: {$customerTotal} customers, ".count($opportunities).' opportunities.');
        }
    }

    // ── Foundation ────────────────────────────────────────────────────────────

    /** @return array<int> */
    private function seedUsers(): array
    {
        $ids = [];
        $specs = [['Admin', env('ADMIN_EMAIL', 'admin@example.com'), 'admin']];
        for ($i = 1; $i <= 3; $i++) {
            $specs[] = ["Sales Manager {$i}", "manager{$i}@noor-estates.com", 'manager'];
        }
        for ($i = 1; $i <= 11; $i++) {
            $specs[] = [$this->personName(), "sales{$i}@noor-estates.com", 'sales_rep'];
        }

        foreach ($specs as [$name, $email, $role]) {
            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password'), 'locale' => mt_rand(0, 1) ? 'ar' : 'en', 'is_active' => true]
            );
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }
            $ids[] = $user->id;
        }

        return $ids;
    }

    private function seedCompanySettings(): void
    {
        CompanySetting::setMany(self::COMPANY);
    }

    /** @return array<string, int> category name => id */
    private function seedCategories(int $adminId): array
    {
        $now = now()->toDateTimeString();
        $roots = [
            ['Residential', 'Apartments, villas and chalets across Egypt and the Gulf', 10],
            ['Commercial', 'Offices and retail units', 20],
        ];
        $children = [
            'Residential' => [
                ['Apartments', 'Apartments in New Cairo, Cairo and the Gulf', 10],
                ['Villas', 'Standalone and townhouse villas', 20],
                ['Chalets', 'North Coast holiday chalets', 30],
            ],
            'Commercial' => [
                ['Offices', 'Office space in the New Capital and business districts', 10],
                ['Retail', 'Shops and mall units', 20],
            ],
        ];

        $ids = [];
        foreach ($roots as [$name, $desc, $sort]) {
            DB::table('product_categories')->updateOrInsert(
                ['name' => $name],
                ['slug' => Str::slug($name), 'parent_id' => null, 'description' => $desc, 'sort_order' => $sort, 'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now]
            );
            $ids[$name] = DB::table('product_categories')->where('name', $name)->value('id');
        }
        foreach ($children as $parent => $list) {
            foreach ($list as [$name, $desc, $sort]) {
                DB::table('product_categories')->updateOrInsert(
                    ['name' => $name],
                    ['slug' => Str::slug($name), 'parent_id' => $ids[$parent], 'description' => $desc, 'sort_order' => $sort, 'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now]
                );
                $ids[$name] = DB::table('product_categories')->where('name', $name)->value('id');
            }
        }

        return $ids;
    }

    private function seedCustomFields(int $adminId): void
    {
        $now = now()->toDateTimeString();

        $productGroup = $this->entityGroup('product', 'Real estate details', 'Unit attributes for property inventory', $adminId);
        $this->definitions($productGroup, $adminId, [
            ['project', 'Project / Compound', 'select', array_keys(self::PROJECTS), true, true, 0],
            ['developer', 'Developer', 'text', null, false, false, 1],
            ['city', 'City', 'select', ['New Cairo', 'Cairo', 'Giza', 'North Coast', 'New Capital', 'Dubai', 'Abu Dhabi', 'Riyadh'], true, true, 2],
            ['area_sqm', 'Area (m²)', 'number', null, false, false, 3],
            ['bedrooms', 'Bedrooms', 'number', null, false, false, 4],
            ['delivery_status', 'Delivery', 'select', ['ready', 'off_plan'], true, true, 5],
        ]);

        $customerGroup = $this->entityGroup('customer', 'Buyer profile', 'MENA buyer preferences from portals', $adminId);
        $this->definitions($customerGroup, $adminId, [
            ['portal_source', 'Portal source', 'select', self::PORTALS, false, true, 0],
            ['budget_range', 'Budget range', 'select', ['<3M', '3-8M', '8-15M', '15M+'], false, true, 1],
            ['preferred_city', 'Preferred city', 'select', ['New Cairo', 'Cairo', 'North Coast', 'New Capital', 'Dubai', 'Riyadh'], false, true, 2],
            ['buyer_type', 'Buyer type', 'select', ['end_user', 'investor'], false, true, 3],
        ]);

        $oppGroup = $this->entityGroup('opportunity', 'Deal profile', 'Financing and purpose for property deals', $adminId);
        $this->definitions($oppGroup, $adminId, [
            ['financing', 'Financing', 'select', ['cash', 'installments', 'mortgage'], false, true, 0],
            ['purpose', 'Purpose', 'select', ['residence', 'investment'], false, true, 1],
        ]);
    }

    private function entityGroup(string $entity, string $name, string $desc, int $adminId): int
    {
        $now = now()->toDateTimeString();
        DB::table('custom_field_groups')->updateOrInsert(
            ['entity_type' => $entity, 'scope_key' => 'entity'],
            ['scope' => 'entity', 'category_id' => null, 'name' => $name, 'description' => $desc, 'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now]
        );

        return (int) DB::table('custom_field_groups')->where('entity_type', $entity)->where('scope_key', 'entity')->value('id');
    }

    /** @param array<array> $defs */
    private function definitions(int $groupId, int $adminId, array $defs): void
    {
        $now = now()->toDateTimeString();
        foreach ($defs as [$name, $label, $type, $options, $required, $filterable, $sort]) {
            DB::table('custom_field_definitions')->updateOrInsert(
                ['group_id' => $groupId, 'name' => $name],
                ['label' => $label, 'type' => $type, 'options' => $options ? json_encode($options) : null, 'is_required' => $required, 'is_filterable' => $filterable, 'sort_order' => $sort, 'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    /**
     * @param  array<string, int>  $categoryIds
     * @return array<array{id:int, name:string, sku:string, unit_price:float}>
     */
    private function seedProducts(int $adminId, array $categoryIds, int $count): array
    {
        $now = now()->toDateTimeString();
        $projectNames = array_keys(self::PROJECTS);
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $project = $projectNames[($i - 1) % count($projectNames)];
            [$developer, $city, $category, $min, $max] = self::PROJECTS[$project];
            $price = round(mt_rand($min, $max) / 1000) * 1000;
            $area = mt_rand(90, 350);
            $beds = $category === 'Offices' || $category === 'Retail' ? mt_rand(0, 2) : mt_rand(2, 5);
            $typeCode = strtoupper(substr($category, 0, 2));
            $countryCode = in_array($city, ['Dubai', 'Abu Dhabi']) ? 'UAE' : (in_array($city, ['Riyadh']) ? 'KSA' : 'EGY');

            $rows[] = [
                'name' => "{$project} {$category} {$area}m² #{$i}",
                'sku' => sprintf('%s-%s-%s-%04d', $countryCode, strtoupper(substr(preg_replace('/[^A-Z]/', '', strtoupper($project)), 0, 3)), $typeCode, $i),
                'description' => "{$beds}-bedroom {$category} unit in {$project}, {$city}.",
                'unit_price' => $price,
                'currency' => self::CURRENCY,
                'category_id' => $categoryIds[$category],
                'is_active' => mt_rand(1, 100) <= 92,
                'created_by' => $adminId,
                'custom_fields' => json_encode([
                    'project' => $project,
                    'developer' => $developer,
                    'city' => $city,
                    'area_sqm' => $area,
                    'bedrooms' => $beds,
                    'delivery_status' => mt_rand(1, 100) <= 55 ? 'ready' : 'off_plan',
                ]),
                'category_custom_fields' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('products')->insert($chunk);
        }

        return DB::table('products')->select('id', 'name', 'sku', 'unit_price')->orderBy('id')->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'sku' => $r->sku, 'unit_price' => (float) $r->unit_price])->all();
    }

    /** @return array<int> */
    private function seedTags(int $adminId): array
    {
        $now = now()->toDateTimeString();
        $tags = [
            ['investor', '#8b5cf6'], ['buyer', '#22c55e'], ['seller', '#f59e0b'],
            ['vip', '#ef4444'], ['off-plan', '#3b82f6'], ['urgent', '#ec4899'],
            ['mortgage', '#14b8a6'], ['cash-buyer', '#84cc16'],
        ];
        foreach ($tags as [$name, $color]) {
            DB::table('tags')->updateOrInsert(['name' => $name], ['color' => $color, 'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now]);
        }

        return DB::table('tags')->whereIn('name', array_column($tags, 0))->pluck('id')->all();
    }

    /** @return array<int> */
    private function seedTemplates(int $adminId): array
    {
        $now = now()->toDateTimeString();
        $templates = [
            ['New Cairo launch', 'New units in {{project}} — {{city}}', '<p>Hello {{first_name}},</p><p>New {{project}} units from {{price}} EGP. Reply on WhatsApp to book a viewing.</p>'],
            ['North Coast summer offer', 'Your chalet in {{project}} awaits', '<p>Hello {{first_name}},</p><p>Sea-view chalets in {{project}} with 10% down payment.</p>'],
            ['Price drop alert', 'Price update: {{project}} {{city}}', '<p>Hello {{first_name}},</p><p>A unit matching your budget ({{budget}}) just dropped in price.</p>'],
            ['Viewing reminder', 'Reminder: viewing tomorrow in {{city}}', '<p>Hello {{first_name}},</p><p>Your viewing in {{project}} is tomorrow at 12pm.</p>'],
            ['Mortgage help', 'Own with 20% down — {{project}}', '<p>Hello {{first_name}},</p><p>Mortgage pre-approval in 48 hours for {{project}} units.</p>'],
            ['Investment ROI', '{{project}} ROI up to 12%', '<p>Hello {{first_name}},</p><p>Investor brief for {{project}} attached. Rental yield 8-12%.</p>'],
            ['New Capital offices', 'Office space in the New Capital', '<p>Hello {{first_name}},</p><p>Grade-A offices from {{price}} EGP, ready 2027.</p>'],
            ['Dubai investor visa', 'Dubai unit + Golden Visa', '<p>Hello {{first_name}},</p><p>{{project}} units qualify for the 10-year Golden Visa.</p>'],
            ['Ramadan offer', 'Ramadan 0% admin fees — {{project}}', '<p>Hello {{first_name}},</p><p>Zero admin fees on {{project}} bookings this month.</p>'],
            ['Re-engagement', 'Still looking in {{city}}, {{first_name}}?', '<p>Hello {{first_name}},</p><p>3 new units match your search in {{city}}.</p>'],
        ];

        foreach ($templates as [$name, $subject, $body]) {
            DB::table('email_templates')->updateOrInsert(
                ['name' => $name],
                ['subject' => $subject, 'body' => $body, 'placeholders' => json_encode($this->placeholders($body)), 'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        return DB::table('email_templates')->whereIn('name', array_column($templates, 0))->pluck('id')->all();
    }

    /** @return array<string> */
    private function placeholders(string $body): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $body, $m);

        return array_values(array_unique($m[1]));
    }

    // ── Customers & pipeline ──────────────────────────────────────────────────

    /**
     * @param  array<int>  $userIds
     * @return array<array{id:int, created_at:string, is_lead:bool, email:string}>
     */
    private function seedCustomers(array $userIds, int $total): array
    {
        $all = [];
        $perChunk = 500;

        for ($offset = 0; $offset < $total; $offset += $perChunk) {
            $size = min($perChunk, $total - $offset);
            $rows = [];
            $emails = [];

            for ($i = 1; $i <= $size; $i++) {
                $seq = $offset + $i;
                $row = $this->customerRow($seq, $userIds);
                $rows[] = $row;
                $emails[] = $row['email'];
            }

            DB::table('customers')->insert($rows);

            $ids = DB::table('customers')->whereIn('email', $emails)->select('id', 'email', 'created_at', 'is_lead')->get();
            foreach ($ids as $r) {
                $all[] = ['id' => $r->id, 'created_at' => (string) $r->created_at, 'is_lead' => (bool) $r->is_lead, 'email' => $r->email];
            }
        }

        return $all;
    }

    /** @param array<int> $userIds */
    private function customerRow(int $seq, array $userIds): array
    {
        $created = $this->randomDate('-3 years', 'now');
        $isLead = mt_rand(1, 100) <= 55;
        $isCompany = mt_rand(1, 100) <= 40;
        $type = $isCompany ? 'company' : 'individual';
        $name = $this->personName();
        [$first, $last] = explode(' ', $name, 2) + [1 => ''];
        $email = sprintf('seed-cust-%06d@example.com', $seq);
        [$city, $state, $country] = self::CITIES[array_rand(self::CITIES)];
        $expectedClose = (clone $created)->addDays(mt_rand(30, 180))->toDateString();
        $lastContacted = $this->clampDate((clone $created)->addDays(mt_rand(0, 900)));

        return [
            'type' => $type,
            'is_lead' => $isLead,
            'lead_status' => $isLead ? $this->weighted(self::LEAD_STATUS_WEIGHTS) : null,
            'status' => $isLead ? 'active' : $this->weighted(['active' => 60, 'at_risk' => 15, 'renewed' => 10, 'paused' => 8, 'churned' => 7]),
            'assigned_to' => $userIds[array_rand($userIds)],
            'created_by' => $userIds[array_rand($userIds)],
            'account_manager_id' => $isLead ? null : $userIds[array_rand($userIds)],
            'source' => $this->weighted(self::SOURCE_WEIGHTS),
            'estimated_value' => mt_rand(500000, 25000000),
            'expected_close_date' => $expectedClose,
            'description' => "Looking for property in {$city}. Budget ".fake()->randomElement(['3-8M', '8-15M', '15M+']).'.',
            'first_name' => $first,
            'last_name' => $last ?: $first,
            'email' => $email,
            'phone' => $this->menaPhone(),
            'job_title' => fake()->randomElement(['Engineer', 'Doctor', 'Business Owner', 'Banker', 'Teacher', null]),
            'birthday' => null,
            'gender' => null,
            'company_name' => $isCompany ? fake()->company().' Real Estate' : null,
            'industry' => mt_rand(1, 100) <= 95 ? 'real_estate' : fake()->randomElement(['technology', 'finance', 'retail', 'healthcare']),
            'website' => null,
            'tax_id' => $isCompany ? 'TAX-'.mt_rand(10000000, 99999999) : null,
            'contact_name' => $name,
            'contact_email' => $email,
            'contact_phone' => $this->menaPhone(),
            'contract_value' => $isLead ? null : mt_rand(2000000, 30000000),
            'health_score' => mt_rand(40, 100),
            'last_contacted_at' => $lastContacted->toDateTimeString(),
            'next_follow_up_at' => $this->clampDate((clone $lastContacted)->addDays(mt_rand(1, 30)))->toDateTimeString(),
            'notes' => null,
            'converted_at' => $isLead ? null : $lastContacted->toDateTimeString(),
            'custom_fields' => json_encode([
                'portal_source' => self::PORTALS[array_rand(self::PORTALS)],
                'budget_range' => fake()->randomElement(['<3M', '3-8M', '8-15M', '15M+']),
                'preferred_city' => fake()->randomElement(['New Cairo', 'Cairo', 'North Coast', 'New Capital', 'Dubai', 'Riyadh']),
                'buyer_type' => mt_rand(1, 100) <= 35 ? 'investor' : 'end_user',
            ]),
            'contacts' => json_encode([[
                'first_name' => $first, 'last_name' => $last ?: $first, 'email' => $email,
                'phone' => $this->menaPhone(), 'title' => null, 'is_primary' => true,
            ]]),
            'addresses' => json_encode([[
                'label' => 'Home', 'type' => 'home', 'line_1' => mt_rand(1, 200).' '.fake()->streetName(),
                'city' => $city, 'state' => $state, 'postal_code' => (string) mt_rand(10000, 99999),
                'country' => $country, 'is_primary' => true,
            ]]),
            'score' => mt_rand(10, 95),
            'score_breakdown' => null,
            'scored_at' => $lastContacted->toDateTimeString(),
            'created_at' => $created->toDateTimeString(),
            'updated_at' => $created->toDateTimeString(),
            'deleted_at' => null,
        ];
    }

    /**
     * @param  array<array{id:int, created_at:string, is_lead:bool, email:string}>  $customers
     * @param  array<int>  $userIds
     * @param  array<array{id:int, name:string, sku:string, unit_price:float}>  $products
     * @return array<array{id:int, customer_id:int, created_at:string, stage:string, amount:float}>
     */
    private function seedOpportunities(array $customers, array $userIds, array $products): array
    {
        $rows = [];
        foreach ($customers as $c) {
            $roll = mt_rand(1, 100);
            $n = $roll <= 60 ? 1 : ($roll <= 85 ? 2 : 3);
            $base = new Carbon($c['created_at']);

            for ($k = 0; $k < $n; $k++) {
                $created = $this->clampDate((clone $base)->addDays(mt_rand(0, 60)));
                $stage = $this->weighted(self::STAGE_WEIGHTS);
                $product = $products[array_rand($products)];
                $amount = $product['unit_price'];
                $closed = in_array($stage, ['won', 'lost'], true);

                $rows[] = [
                    'customer_id' => $c['id'],
                    'title' => $this->oppTitle($product['name']),
                    'type' => fake()->randomElement(['initial', 'initial', 'upsell', 'cross_sell', 'renewal']),
                    'stage' => $stage,
                    'amount' => $amount,
                    'currency' => self::CURRENCY,
                    'probability' => $stage === 'won' ? 100 : ($stage === 'lost' ? 0 : mt_rand(10, 90)),
                    'expected_close_date' => (clone $created)->addDays(mt_rand(30, 180))->toDateString(),
                    'start_date' => null,
                    'end_date' => null,
                    'closed_at' => $closed ? $this->clampDate((clone $created)->addDays(mt_rand(10, 120)))->toDateTimeString() : null,
                    'assigned_to' => $userIds[array_rand($userIds)],
                    'created_by' => $userIds[array_rand($userIds)],
                    'notes' => null,
                    'custom_fields' => json_encode([
                        'financing' => fake()->randomElement(['cash', 'installments', 'installments', 'mortgage']),
                        'purpose' => mt_rand(1, 100) <= 35 ? 'investment' : 'residence',
                    ]),
                    'created_at' => $created->toDateTimeString(),
                    'updated_at' => $created->toDateTimeString(),
                    '_customer_created' => (string) $c['created_at'],
                ];
            }
        }

        $insert = array_map(fn ($r) => array_diff_key($r, ['_customer_created' => 1]), $rows);
        foreach (array_chunk($insert, 1000) as $chunk) {
            DB::table('opportunities')->insert($chunk);
        }

        // Map back ids in creation order per customer.
        $all = DB::table('opportunities')->orderBy('id')->select('id', 'customer_id', 'created_at', 'stage', 'amount')->get();
        $out = [];
        $converted = [];
        foreach ($all as $o) {
            $out[] = ['id' => $o->id, 'customer_id' => $o->customer_id, 'created_at' => (string) $o->created_at, 'stage' => $o->stage, 'amount' => (float) $o->amount];
            if ($o->stage === 'won') {
                $converted[$o->customer_id] = (string) $o->created_at;
            }
        }

        // Won opportunities convert their lead customer.
        foreach (array_chunk(array_keys($converted), 1000, true) as $chunk) {
            foreach ($chunk as $cid) {
                DB::table('customers')->where('id', $cid)->where('is_lead', true)->update([
                    'is_lead' => false, 'lead_status' => null, 'status' => 'active',
                    'converted_at' => $converted[$cid], 'updated_at' => $converted[$cid],
                ]);
            }
        }

        return $out;
    }

    /**
     * @param  array<array{id:int, customer_id:int, created_at:string, stage:string}>  $opps
     * @param  array<array{id:int, name:string, sku:string, unit_price:float}>  $products
     */
    private function seedOpportunityProducts(array $opps, array $products): void
    {
        $rows = [];
        foreach ($opps as $o) {
            $n = mt_rand(1, 3);
            $picked = array_rand($products, min($n, count($products)));
            $picked = is_array($picked) ? $picked : [$picked];
            foreach ($picked as $pi) {
                $rows[] = [
                    'opportunity_id' => $o['id'],
                    'product_id' => $products[$pi]['id'],
                    'quantity' => 1,
                    'unit_price' => $products[$pi]['unit_price'],
                    'notes' => null,
                    'created_at' => $o['created_at'],
                    'updated_at' => $o['created_at'],
                ];
            }
        }

        // De-dupe on (opportunity_id, product_id) unique key.
        $seen = [];
        $rows = array_values(array_filter($rows, function ($r) use (&$seen) {
            $k = $r['opportunity_id'].':'.$r['product_id'];

            return ! isset($seen[$k]) && ($seen[$k] = true);
        }));

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('opportunity_products')->insert($chunk);
        }
    }

    /** @param array<array{id:int, created_at:string}> $customers @param array<int> $userIds */
    private function seedCustomerActivities(array $customers, array $userIds, int $per): void
    {
        $types = ['follow_up', 'follow_up', 'check_in', 'whatsapp', 'support', 'review', 'complaint', 'general'];
        $rows = [];

        foreach ($customers as $c) {
            $base = new Carbon($c['created_at']);
            for ($k = 0; $k < $per; $k++) {
                $due = $this->clampDate((clone $base)->addDays(mt_rand(0, 900)));
                $done = mt_rand(1, 100) <= 80;
                $rows[] = [
                    'customer_id' => $c['id'],
                    'user_id' => $userIds[array_rand($userIds)],
                    'type' => $types[array_rand($types)],
                    'channel' => $this->weighted(self::CHANNEL_WEIGHTS),
                    'subject' => fake()->randomElement(['WhatsApp follow-up on unit viewing', 'Call: budget and area check', 'Viewing scheduled', 'Portal inquiry response', 'Price negotiation call', 'Documents collection']),
                    'description' => fake()->sentence(),
                    'outcome' => $done ? fake()->randomElement(['positive', 'positive', 'neutral', 'negative', 'escalated']) : null,
                    'due_date' => $due->toDateTimeString(),
                    'reminder_at' => null,
                    'reminder_sent_at' => null,
                    'completed_at' => $done ? $due->toDateTimeString() : null,
                    'created_at' => $due->toDateTimeString(),
                    'updated_at' => $due->toDateTimeString(),
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('customer_activities')->insert($chunk);
        }
    }

    /** @param array<array{id:int, created_at:string}> $opps @param array<int> $userIds */
    private function seedOpportunityActivities(array $opps, array $userIds, int $per): void
    {
        $types = ['call', 'email', 'meeting', 'task', 'whatsapp', 'whatsapp', 'note'];
        $rows = [];

        foreach ($opps as $o) {
            $base = new Carbon($o['created_at']);
            for ($k = 0; $k < $per; $k++) {
                $due = $this->clampDate((clone $base)->addDays(mt_rand(0, 300)));
                $done = mt_rand(1, 100) <= 75;
                $rows[] = [
                    'opportunity_id' => $o['id'],
                    'user_id' => $userIds[array_rand($userIds)],
                    'type' => $types[array_rand($types)],
                    'channel' => $this->weighted(self::CHANNEL_WEIGHTS),
                    'subject' => fake()->randomElement(['Offer sent on WhatsApp', 'Site visit', 'Down payment discussion', 'Contract review meeting', 'Mortgage documents follow-up']),
                    'description' => fake()->sentence(),
                    'outcome' => $done ? fake()->randomElement(['positive', 'positive', 'neutral', 'negative']) : null,
                    'due_date' => $due->toDateTimeString(),
                    'reminder_at' => null,
                    'reminder_sent_at' => null,
                    'completed_at' => $done ? $due->toDateTimeString() : null,
                    'created_at' => $due->toDateTimeString(),
                    'updated_at' => $due->toDateTimeString(),
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('opportunity_activities')->insert($chunk);
        }
    }

    /**
     * @param  array<array{id:int, customer_id:int, created_at:string, stage:string, amount:float}>  $opps
     * @param  array<int>  $userIds
     * @param  array<array{id:int, name:string, sku:string, unit_price:float}>  $products
     * @return array<array{id:int, opportunity_id:int, customer_id:int, total:float, created_at:string}>
     */
    private function seedQuotations(array $opps, array $userIds, array $products): array
    {
        $rows = [];
        $seq = 0;
        foreach ($opps as $i => $o) {
            if ($i % 2 !== 0) {
                continue;
            }
            $seq++;
            $created = new Carbon($o['created_at']);
            $subtotal = round($products[array_rand($products)]['unit_price'], 2);
            $tax = round($subtotal * 0.14, 2);
            $sent = mt_rand(1, 100) <= 60;
            $rows[] = [
                'opportunity_id' => $o['id'],
                'customer_id' => $o['customer_id'],
                'created_by' => $userIds[array_rand($userIds)],
                'sent_by' => $sent ? $userIds[array_rand($userIds)] : null,
                'quotation_number' => sprintf('QT-%06d', $seq),
                'status' => $sent ? 'sent' : 'draft',
                'currency' => self::CURRENCY,
                'subtotal' => $subtotal,
                'tax_percent' => 14,
                'tax_amount' => $tax,
                'discount_amount' => 0,
                'total' => $subtotal + $tax,
                'valid_until' => (clone $created)->addDays(30)->toDateString(),
                'notes' => 'Offer presented by Noor Estates. Prices per developer price list; maintenance deposit and club membership where applicable.',
                'terms' => 'Valid 30 days. 10% reservation deposit payable to the developer to block the unit. Brokerage commission settled by the developer.',
                'sent_at' => $sent ? $created->toDateTimeString() : null,
                'created_at' => $created->toDateTimeString(),
                'updated_at' => $created->toDateTimeString(),
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('quotations')->insert($chunk);
        }

        $saved = DB::table('quotations')->orderBy('id')->select('id', 'opportunity_id', 'customer_id', 'total', 'created_at')->get();
        $out = [];
        $items = [];
        foreach ($saved as $q) {
            $out[] = ['id' => $q->id, 'opportunity_id' => $q->opportunity_id, 'customer_id' => $q->customer_id, 'total' => (float) $q->total, 'created_at' => (string) $q->created_at];
            $n = mt_rand(2, 3);
            $picked = array_rand($products, min($n, count($products)));
            $picked = is_array($picked) ? $picked : [$picked];
            foreach ($picked as $pi) {
                $p = $products[$pi];
                $items[] = [
                    'quotation_id' => $q->id,
                    'product_id' => $p['id'],
                    'product_name' => $p['name'],
                    'sku' => $p['sku'],
                    'quantity' => 1,
                    'unit_price' => $p['unit_price'],
                    'subtotal' => $p['unit_price'],
                    'notes' => null,
                    'created_at' => (string) $q->created_at,
                    'updated_at' => (string) $q->created_at,
                ];
            }
        }

        foreach (array_chunk($items, 1000) as $chunk) {
            DB::table('quotation_items')->insert($chunk);
        }

        return $out;
    }

    /**
     * Broker commission invoices.
     *
     * Noor Estates is a brokerage: it never bills the property value (that is
     * the developer's invoice). On every won deal the developer owes the
     * broker a 2-3% commission, VAT-taxable as a service at 14%. Each invoice
     * carries a single line referencing the exact unit sold.
     *
     * @param  array<array{id:int, customer_id:int, created_at:string, stage:string, amount:float}>  $opps
     * @param  array<array{id:int, opportunity_id:int, customer_id:int, total:float, created_at:string}>  $quotations
     * @param  array<int>  $userIds
     */
    private function seedInvoices(array $opps, array $quotations, array $userIds): void
    {
        $quoteByOpp = [];
        foreach ($quotations as $q) {
            $quoteByOpp[$q['opportunity_id']] = $q['id'];
        }

        // First unit attached to each opportunity, for the commission line.
        $unitByOpp = DB::table('opportunity_products')
            ->join('products', 'products.id', '=', 'opportunity_products.product_id')
            ->select('opportunity_products.opportunity_id', 'products.id', 'products.name', 'products.sku')
            ->orderBy('opportunity_products.opportunity_id')
            ->orderBy('products.id')
            ->get()
            ->keyBy('opportunity_id');

        $rows = [];
        $rateByOpp = [];
        $seq = 0;
        foreach ($opps as $o) {
            if ($o['stage'] !== 'won') {
                continue;
            }
            $seq++;
            $created = new Carbon($o['created_at']);
            $rate = fake()->randomElement([2.0, 2.0, 2.5, 2.5, 3.0]);
            $rateByOpp[$o['id']] = $rate;
            $subtotal = round($o['amount'] * $rate / 100, 2);
            $tax = round($subtotal * 0.14, 2);
            $rows[] = [
                'opportunity_id' => $o['id'],
                'customer_id' => $o['customer_id'],
                'quotation_id' => $quoteByOpp[$o['id']] ?? null,
                'created_by' => $userIds[array_rand($userIds)],
                'invoice_number' => sprintf('INV-%06d', $seq),
                'status' => fake()->randomElement(['issued', 'issued', 'paid', 'paid', 'overdue']),
                'currency' => self::CURRENCY,
                'subtotal' => $subtotal,
                'tax_percent' => 14,
                'tax_amount' => $tax,
                'discount_amount' => 0,
                'total' => $subtotal + $tax,
                'issued_at' => (clone $created)->addDays(mt_rand(5, 60))->toDateString(),
                'created_at' => $created->toDateTimeString(),
                'updated_at' => $created->toDateTimeString(),
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('invoices')->insert($chunk);
        }

        $saved = DB::table('invoices')->orderBy('id')->select('id', 'opportunity_id', 'created_at', 'subtotal')->get();
        $items = [];
        foreach ($saved as $inv) {
            $rate = $rateByOpp[$inv->opportunity_id] ?? null;
            $unit = $unitByOpp->get($inv->opportunity_id);
            $items[] = [
                'invoice_id' => $inv->id,
                'product_id' => $unit?->id,
                'product_name' => $unit ? $unit->name : 'Brokerage commission',
                'sku' => $unit?->sku,
                'quantity' => 1,
                'unit_price' => $inv->subtotal,
                'subtotal' => $inv->subtotal,
                'notes' => $rate !== null ? "Brokerage commission {$rate}% on closed deal" : null,
                'created_at' => (string) $inv->created_at,
                'updated_at' => (string) $inv->created_at,
            ];
        }

        foreach (array_chunk($items, 1000) as $chunk) {
            DB::table('invoice_items')->insert($chunk);
        }
    }

    // ── Engagement ────────────────────────────────────────────────────────────

    /** @param array<array{id:int}> $customers @param array<array{id:int}> $opps @param array<int> $userIds */
    private function seedTasks(array $customers, array $opps, array $userIds, int $total): void
    {
        $rows = [];
        $oppCount = count($opps);
        for ($i = 0; $i < $total; $i++) {
            $useOpp = $oppCount > 0 && mt_rand(1, 100) <= 30;
            $target = $useOpp ? $opps[array_rand($opps)] : $customers[array_rand($customers)];
            $base = new Carbon($target['created_at']);
            $due = $this->clampDate((clone $base)->addDays(mt_rand(1, 120)));
            $status = $this->weighted(['pending' => 40, 'in_progress' => 20, 'completed' => 35, 'cancelled' => 5]);
            $rows[] = [
                'user_id' => $userIds[array_rand($userIds)],
                'taskable_type' => $useOpp ? Opportunity::class : Customer::class,
                'taskable_id' => $target['id'],
                'title' => fake()->randomElement(['Call back buyer', 'Send unit brochure on WhatsApp', 'Schedule site visit', 'Collect down payment receipt', 'Prepare contract draft', 'Confirm mortgage pre-approval']),
                'description' => fake()->sentence(),
                'priority' => fake()->randomElement(['low', 'medium', 'medium', 'high', 'urgent']),
                'status' => $status,
                'due_date' => $due->toDateTimeString(),
                'completed_at' => $status === 'completed' ? $due->toDateTimeString() : null,
                'created_by' => $userIds[array_rand($userIds)],
                'created_at' => $base->toDateTimeString(),
                'updated_at' => $due->toDateTimeString(),
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('tasks')->insert($chunk);
        }
    }

    /** @param array<array{id:int}> $customers @param array<array{id:int}> $opps @param array<int> $userIds */
    private function seedNotes(array $customers, array $opps, array $userIds, int $total): void
    {
        $rows = [];
        $oppCount = count($opps);
        for ($i = 0; $i < $total; $i++) {
            $useOpp = $oppCount > 0 && mt_rand(1, 100) <= 30;
            $target = $useOpp ? $opps[array_rand($opps)] : $customers[array_rand($customers)];
            $created = $this->clampDate((new Carbon($target['created_at']))->addDays(mt_rand(0, 200)));
            $rows[] = [
                'notable_type' => $useOpp ? Opportunity::class : Customer::class,
                'notable_id' => $target['id'],
                'parent_id' => null,
                'user_id' => $userIds[array_rand($userIds)],
                'body' => fake()->randomElement([
                    'Buyer prefers high floor, sea view, installments over 7 years.',
                    'Seller agreed to include kitchen appliances. Follow up Thursday.',
                    'Client compared Marassi vs Hacienda — leaning Marassi phase 2.',
                    'Sent payment plan on WhatsApp. Waiting for ID + proof of income.',
                    'Viewing done. Client wants same layout in lower price bracket.',
                ]),
                'is_pinned' => mt_rand(1, 100) <= 5,
                'created_at' => $created->toDateTimeString(),
                'updated_at' => $created->toDateTimeString(),
                'deleted_at' => null,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('notes')->insert($chunk);
        }

        // 10% manager replies on random top-level notes.
        $parents = DB::table('notes')->whereNull('parent_id')->inRandomOrder()->limit((int) ($total * 0.1))->select('id', 'notable_type', 'notable_id', 'created_at')->get();
        $replies = [];
        foreach ($parents as $p) {
            $created = $this->clampDate((new Carbon($p->created_at))->addDays(mt_rand(0, 7)));
            $replies[] = [
                'notable_type' => $p->notable_type,
                'notable_id' => $p->notable_id,
                'parent_id' => $p->id,
                'user_id' => $userIds[array_rand($userIds)],
                'body' => 'Noted — added to this week\'s follow-up list.',
                'is_pinned' => false,
                'created_at' => $created->toDateTimeString(),
                'updated_at' => $created->toDateTimeString(),
                'deleted_at' => null,
            ];
        }
        if ($replies !== []) {
            DB::table('notes')->insert($replies);
        }
    }

    /** @param array<array{id:int}> $customers @param array<array{id:int}> $opps @param array<int> $userIds */
    private function seedAttachments(array $customers, array $opps, array $userIds, int $total): void
    {
        $files = [
            ['booking-form.pdf', 'application/pdf'], ['unit-brochure.pdf', 'application/pdf'],
            ['payment-plan.pdf', 'application/pdf'], ['id-copy.jpg', 'image/jpeg'],
            ['contract-draft.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['site-plan.png', 'image/png'],
        ];
        $oppCount = count($opps);
        $rows = [];
        for ($i = 0; $i < $total; $i++) {
            $useOpp = $oppCount > 0 && mt_rand(1, 100) <= 40;
            $target = $useOpp ? $opps[array_rand($opps)] : $customers[array_rand($customers)];
            [$name, $mime] = $files[array_rand($files)];
            $stored = Str::uuid().'-'.$name;
            $rows[] = [
                'attachable_type' => $useOpp ? Opportunity::class : Customer::class,
                'attachable_id' => $target['id'],
                'uploaded_by' => $userIds[array_rand($userIds)],
                'original_name' => $name,
                'stored_name' => $stored,
                'mime_type' => $mime,
                'size' => mt_rand(50_000, 5_000_000),
                'disk' => 'local',
                'path' => 'attachments/'.$stored,
                'created_at' => $target['created_at'],
                'updated_at' => $target['created_at'],
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('attachments')->insert($chunk);
        }
    }

    /** @param array<array{id:int}> $customers @param array<array{id:int}> $opps @param array<int> $tagIds */
    private function seedTaggables(array $customers, array $tagIds, array $opps): void
    {
        $rows = [];
        foreach ($customers as $c) {
            foreach ((array) array_rand($tagIds, mt_rand(1, 3)) as $ti) {
                $rows[] = ['tag_id' => $tagIds[$ti], 'taggable_type' => Customer::class, 'taggable_id' => $c['id']];
            }
        }
        foreach ($opps as $o) {
            if (mt_rand(1, 100) > 60) {
                continue;
            }
            foreach ((array) array_rand($tagIds, mt_rand(1, 2)) as $ti) {
                $rows[] = ['tag_id' => $tagIds[$ti], 'taggable_type' => Opportunity::class, 'taggable_id' => $o['id']];
            }
        }

        $seen = [];
        $rows = array_values(array_filter($rows, function ($r) use (&$seen) {
            $k = $r['tag_id'].':'.$r['taggable_type'].':'.$r['taggable_id'];

            return ! isset($seen[$k]) && ($seen[$k] = true);
        }));

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('taggables')->insert($chunk);
        }
    }

    /**
     * @param  array<array{id:int, email:string, created_at:string}>  $customers
     * @param  array<int>  $templateIds
     */
    private function seedCampaigns(array $customers, array $templateIds, int $adminId, int $count, int $perCampaign): void
    {
        $names = ['New Cairo launch blast', 'North Coast summer push', 'Price-drop alerts', 'New Capital offices', 'Dubai investor visas', 'Ramadan offer', 'Weekly matches', 'Re-engagement wave'];
        $now = now();

        for ($i = 0; $i < $count; $i++) {
            $scheduled = $this->randomDate('-2 years', 'now');
            $sent = $scheduled->lt($now->copy()->subDay());
            $status = $sent ? 'sent' : fake()->randomElement(['draft', 'scheduled', 'scheduled', 'cancelled']);
            $campaignId = DB::table('campaigns')->insertGetId([
                'name' => $names[$i % count($names)].' #'.($i + 1),
                'email_template_id' => $templateIds[array_rand($templateIds)],
                'status' => $status,
                'segment_filters' => json_encode(['preferred_city' => fake()->randomElement(['New Cairo', 'North Coast', 'Dubai'])]),
                'scheduled_at' => $scheduled->toDateTimeString(),
                'sent_at' => $sent ? $scheduled->toDateTimeString() : null,
                'created_by' => $adminId,
                'created_at' => $scheduled->toDateTimeString(),
                'updated_at' => $scheduled->toDateTimeString(),
            ]);

            $pool = $customers;
            shuffle($pool);
            $slice = array_slice($pool, 0, min($perCampaign, count($pool)));
            $rows = [];
            foreach ($slice as $c) {
                $s = $sent ? $this->weighted(['sent' => 80, 'failed' => 5, 'pending' => 15]) : 'pending';
                $rows[] = [
                    'campaign_id' => $campaignId,
                    'customer_id' => $c['id'],
                    'email' => $c['email'],
                    'status' => $s,
                    'sent_at' => $s === 'sent' ? $scheduled->toDateTimeString() : null,
                    'failed_reason' => $s === 'failed' ? 'SMTP bounce' : null,
                    'created_at' => $scheduled->toDateTimeString(),
                    'updated_at' => $scheduled->toDateTimeString(),
                ];
            }
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('campaign_recipients')->insert($chunk);
            }
        }
    }

    private function seedOpsFootprint(int $adminId): void
    {
        $now = now()->toDateTimeString();
        foreach (['Portal webhook (Bayut)' => 'https://crm.noor-estates.com/hooks/bayut', 'Portal webhook (Property Finder)' => 'https://crm.noor-estates.com/hooks/pf'] as $desc => $url) {
            DB::table('webhook_endpoints')->updateOrInsert(
                ['url' => $url],
                ['secret' => Crypt::encryptString(Str::random(32)), 'events' => json_encode(['customer.created']), 'is_active' => false, 'description' => $desc, 'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        for ($i = 1; $i <= 5; $i++) {
            DB::table('imports')->insert([
                'user_id' => $adminId, 'entity_type' => 'customer', 'original_filename' => "portal-leads-{$i}.csv", 'status' => 'completed',
                'total_rows' => 500, 'processed_rows' => 500, 'successful_rows' => 490, 'failed_rows' => 10,
                'errors' => null, 'completed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('async_jobs')->insert([
                'user_id' => $adminId, 'type' => 'report', 'status' => 'completed',
                'input' => json_encode([]), 'result' => json_encode([]), 'error' => null,
                'progress' => 100, 'started_at' => $now, 'completed_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function personName(): string
    {
        if (mt_rand(1, 100) <= 70) {
            return self::AR_FIRST[array_rand(self::AR_FIRST)].' '.self::AR_LAST[array_rand(self::AR_LAST)];
        }

        return self::EN_FIRST[array_rand(self::EN_FIRST)].' '.self::EN_LAST[array_rand(self::EN_LAST)];
    }

    private function menaPhone(): string
    {
        $roll = mt_rand(1, 100);
        if ($roll <= 60) {
            return '+20 1'.mt_rand(100000000, 999999999);
        }
        if ($roll <= 80) {
            return '+971 5'.mt_rand(10000000, 99999999);
        }

        return '+966 5'.mt_rand(10000000, 99999999);
    }

    private function oppTitle(string $productName): string
    {
        return fake()->randomElement(['Buy: ', 'Invest: ', 'Book: ']).Str::limit($productName, 60);
    }

    /** @param array<string, int> $weights */
    private function weighted(array $weights): string
    {
        $total = array_sum($weights);
        $roll = mt_rand(1, $total);
        foreach ($weights as $key => $w) {
            $roll -= $w;
            if ($roll <= 0) {
                return $key;
            }
        }

        return (string) array_key_first($weights);
    }

    private function randomDate(string $from, string $to): Carbon
    {
        $min = (new Carbon($from))->getTimestamp();
        $max = (new Carbon($to))->getTimestamp();

        return Carbon::createFromTimestamp(mt_rand($min, $max));
    }

    private function clampDate(Carbon $date): Carbon
    {
        $now = Carbon::now();
        if ($date->gt($now)) {
            return $now->copy()->subHours(mt_rand(1, 72));
        }
        $min = Carbon::now()->subYears(3);
        if ($date->lt($min)) {
            return $min->copy()->addDays(mt_rand(0, 30));
        }

        return $date;
    }
}
