<?php

namespace App\Services;

use App\AI\Agents\CrmAnalyticsAgent;
use App\Models\Customer;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\ValidationException;

class AiChatService
{
    public function __construct(
        private AiSettingsResolver $aiSettings = new AiSettingsResolver,
        private ReportService $reports = new ReportService,
        private DashboardService $dashboard = new DashboardService,
        private SearchService $search = new SearchService,
    ) {}

    /**
     * Answer a reporting/analytics question with grounded CRM aggregates.
     *
     * The user is passed as an ID (not a model) so the payload survives
     * JSON serialization in the async-job queue.
     *
     * @return array{answer: string, sources: string[], locale: string, generated_at: string}
     */
    public function answerWithAi(array $data, int $userId): array
    {
        $user = User::findOrFail($userId);
        $locale = $data['language'] ?? App::getLocale();
        $languageName = $locale === 'ar' ? 'Arabic' : 'English';
        $scope = $data['scope'] ?? 'all';

        $snapshot = $this->buildSnapshot($data, $user, $scope);
        $sources = array_keys($snapshot);

        $productLabels = CompanySettingsService::productLabels(
            $locale,
            __('reports.product', [], $locale),
            __('reports.products', [], $locale),
        );
        $agent = CrmAnalyticsAgent::make(
            languageName: $languageName,
            scope: $scope,
            productSingular: $productLabels['singular'],
            productPlural: $productLabels['plural'],
        );
        $ai = $this->aiSettings->applyToConfig();

        $promptText = 'Question: '.$data['question']
            ."\n\nCRM snapshot (JSON, aggregates only):\n"
            .json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $scopedUserId = isset($data['user_id']) && is_numeric($data['user_id']) ? (int) $data['user_id'] : null;
        if ($scopedUserId) {
            $scopedUser = User::find($scopedUserId);
            if ($scopedUser) {
                $promptText .= $locale === 'ar'
                    ? "\n\nملاحظة النطاق: كل الأرقام في اللقطة تخص المستخدم {$scopedUser->name} فقط. اذكر اسمه في الإجابة."
                    : "\n\nScope note: every metric in the snapshot is scoped to user {$scopedUser->name} only. Mention their name in the answer.";
            }
        }

        $scopedCustomerId = isset($data['customer_id']) && is_numeric($data['customer_id']) ? (int) $data['customer_id'] : null;
        if ($scopedCustomerId) {
            $scopedCustomer = Customer::find($scopedCustomerId);
            if ($scopedCustomer) {
                $customerName = $scopedCustomer->display_name ?? "#{$scopedCustomer->id}";
                $promptText .= $locale === 'ar'
                    ? "\n\nملاحظة النطاق: اللقطة مقيدة بالعميل {$customerName} فقط."
                    : "\n\nScope note: the snapshot is restricted to customer {$customerName} only.";
            }
        }

        try {
            $response = $agent->prompt($promptText, model: $ai['model']);
            $answer = trim($response->text);
        } catch (\Throwable $e) {
            logger()->error('AI chat answer failed', [
                'message' => $e->getMessage(),
                'scope' => $scope,
            ]);

            throw ValidationException::withMessages([
                'ai' => __('messages.ai_generation_failed'),
            ]);
        }

        return [
            'answer' => $answer,
            'sources' => $sources,
            'locale' => $locale,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * @return string[]
     */
    public function suggestedQuestions(string $locale = 'en'): array
    {
        $questions = __('ai_chat.suggested', [], $locale);
        $questions = is_array($questions) ? array_values($questions) : [];

        $plural = CompanySettingsService::productLabels(
            $locale,
            __('reports.product', [], $locale),
            __('reports.products', [], $locale),
        )['plural'];

        return array_map(
            fn ($question) => is_string($question) ? str_replace(':products', $plural, $question) : $question,
            $questions,
        );
    }

    private function buildSnapshot(array $data, User $user, string $scope): array
    {
        $filters = array_filter([
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
        ]);

        $scopedUserId = isset($data['user_id']) && is_numeric($data['user_id']) ? (int) $data['user_id'] : null;
        $scopedCustomerId = isset($data['customer_id']) && is_numeric($data['customer_id']) ? (int) $data['customer_id'] : null;

        $snapshot = [];

        $include = fn (string $key): bool => $scope === 'all' || $scope === $key;

        if ($include('reports') || $include('pipeline')) {
            $pipelineFilters = $filters;
            if ($scopedUserId) {
                $pipelineFilters['assigned_to'] = $scopedUserId;
            }
            if ($scopedCustomerId) {
                $pipelineFilters['customer_id'] = $scopedCustomerId;
            }
            $pipeline = $this->reports->leadPipeline($pipelineFilters);
            $snapshot['lead_pipeline'] = [
                'summary' => $pipeline['summary'],
                'by_status' => $pipeline['data']['by_status'] ?? [],
                'by_source' => array_slice($pipeline['data']['by_source'] ?? [], 0, 5),
            ];
        }

        if ($include('reports') || $include('customers')) {
            $customerFilters = $filters;
            if ($scopedUserId) {
                $customerFilters['account_manager_id'] = $scopedUserId;
            }
            if ($scopedCustomerId) {
                $customerFilters['customer_id'] = $scopedCustomerId;
            }
            if (isset($data['customer_type']) && in_array($data['customer_type'], ['individual', 'company'], true)) {
                $customerFilters['type'] = $data['customer_type'];
            }
            $customer = $this->reports->customerReport($customerFilters);
            $snapshot['customers'] = $customer['summary'];
        }

        if ($include('reports') || $include('pipeline')) {
            $performanceFilters = $filters;
            if ($scopedUserId) {
                $performanceFilters['user_id'] = $scopedUserId;
            }
            $userPerformance = $this->reports->userPerformance($performanceFilters);
            $snapshot['user_performance'] = $userPerformance['summary'];
            $snapshot['top_performers'] = array_slice($userPerformance['data'] ?? [], 0, 5);
            $snapshot['open_opportunities'] = Opportunity::query()
                ->open()
                ->when($scopedUserId, fn ($query) => $query->where('assigned_to', $scopedUserId))
                ->with('customer')
                ->latest('updated_at')
                ->take(15)
                ->get()
                ->map(fn ($opp) => [
                    'id' => $opp->id,
                    'title' => $opp->title,
                    'stage' => $opp->stage,
                    'amount' => (string) $opp->amount,
                    'currency' => $opp->currency,
                    'customer' => $opp->customer?->display_name,
                ])
                ->values()
                ->all();
        }

        if ($include('reports') || $include('products')) {
            $product = $this->reports->productPerformance($filters);
            $productRows = collect($product['data'] ?? []);
            $withSales = $productRows->filter(fn ($row) => $row['booked_sales'] !== [])->values();
            $withInterest = $productRows->filter(fn ($row) => $row['booked_sales'] === []
                && $row['open_pipeline']['opportunities_count'] > 0)->values();
            $snapshot['products'] = [
                'summary' => $product['summary'],
                'top_products' => $withSales->concat($withInterest)->take(8)
                    ->map(fn ($row) => [
                        'name' => $row['product']['name'],
                        'sku' => $row['product']['sku'],
                        'category' => $row['product']['category']['name'] ?? null,
                        'booked_sales' => $row['booked_sales'],
                        'open_pipeline' => $row['open_pipeline'],
                    ])
                    ->values()
                    ->all(),
            ];
        }

        if ($include('reports') || $include('products')) {
            $userProductFilters = $filters;
            if ($scopedUserId) {
                $userProductFilters['user_id'] = $scopedUserId;
            }
            $userProduct = $this->reports->userProductPerformance($userProductFilters);
            $snapshot['user_product_performance'] = [
                'summary' => $userProduct['summary'],
                'top_rows' => collect($userProduct['data'] ?? [])
                    ->sortByDesc(fn ($row) => collect($row['booked_value_by_currency'])->sum(fn ($item) => (float) $item['value']))
                    ->take(8)
                    ->map(fn ($row) => [
                        'user' => $row['user']['name'],
                        'product' => $row['product']['name'],
                        'opportunities_count' => $row['opportunities_count'],
                        'won_count' => $row['won_count'],
                        'open_count' => $row['open_count'],
                        'quantity' => $row['quantity'],
                        'booked_value_by_currency' => $row['booked_value_by_currency'],
                    ])
                    ->values()
                    ->all(),
            ];
        }

        if ($include('reports')) {
            $dashboard = $this->dashboard->index($filters, $user);
            $snapshot['dashboard'] = $dashboard;

            $salesFilters = $filters;
            if ($scopedUserId) {
                $salesFilters['user_id'] = $scopedUserId;
            }
            $sales = $this->reports->salesReport($salesFilters);
            $snapshot['sales'] = [
                'summary' => $sales['summary'],
                'periods' => collect($sales['data'] ?? [])
                    ->sortByDesc('period')
                    ->take(24)
                    ->map(fn ($row) => [
                        'period' => $row['period'],
                        'won_count' => $row['won_count'],
                        'revenue_by_currency' => $row['revenue_by_currency'],
                        'open_count' => $row['open_count'],
                    ])
                    ->values()
                    ->all(),
            ];
        }

        if ($scope === 'all' && isset($data['question']) && mb_strlen($data['question']) >= 3) {
            $hits = $this->search->search($data['question'], ['customers', 'leads', 'opportunities'], 5, $user);
            $hitItems = collect($hits['results'] ?? [])
                ->flatMap(fn ($items) => $items)
                ->take(5)
                ->map(fn ($item) => [
                    'id' => $item['id'] ?? null,
                    'type' => $item['type'] ?? null,
                    'title' => $item['title'] ?? null,
                    'status' => $item['status'] ?? null,
                ]);
            $snapshot['search_hits'] = $this->withOpportunityAmounts($hitItems)->values()->all();
        }

        if (isset($data['question']) && mb_strlen($data['question']) >= 3) {
            $snapshot['referenced_opportunities'] = $this->findReferencedOpportunities($data['question']);
        }

        return $snapshot;
    }

    /**
     * Attach stored amounts to opportunity search hits so the model quotes
     * the opportunity amount instead of nearby estimates.
     *
     * @param  Collection<int, array>  $hits
     * @return Collection<int, array>
     */
    private function withOpportunityAmounts($hits)
    {
        $ids = $hits->where('type', 'opportunity')->pluck('id')->filter()->values();

        if ($ids->isEmpty()) {
            return $hits;
        }

        $amounts = Opportunity::query()->whereIn('id', $ids->all())
            ->get(['id', 'amount', 'currency'])
            ->keyBy('id');

        return $hits->map(fn ($item) => $item['type'] === 'opportunity' && isset($amounts[$item['id']])
            ? [...$item,
                'amount' => (string) $amounts[$item['id']]->amount,
                'currency' => $amounts[$item['id']]->currency,
            ]
            : $item);
    }

    /**
     * Load opportunities explicitly referenced in the question (by ID or URL)
     * with their stored amounts.
     *
     * @return list<array{id: int, title: string, stage: string, amount: string, currency: string, customer: string|null}>
     */
    private function findReferencedOpportunities(string $question): array
    {
        $normalized = strtr($question, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        preg_match_all('/opportunit(?:y|ies)[^\d]*(\d+)/i', $normalized, $matches);
        preg_match_all('/customers\/(\d+)\/opportunities\/(\d+)/i', $normalized, $urlMatches);
        preg_match_all('/فرص[ةه][^\d]*(\d+)/u', $normalized, $arabicMatches);

        $ids = collect(array_merge($matches[1] ?? [], $urlMatches[2] ?? [], $arabicMatches[1] ?? []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->take(5)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Opportunity::query()
            ->whereIn('id', $ids->all())
            ->with('customer')
            ->get()
            ->map(fn ($opp) => [
                'id' => $opp->id,
                'title' => $opp->title,
                'stage' => $opp->stage,
                'amount' => (string) $opp->amount,
                'currency' => $opp->currency,
                'customer' => $opp->customer?->display_name,
            ])
            ->values()
            ->all();
    }
}
