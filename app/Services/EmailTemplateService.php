<?php

namespace App\Services;

use App\AI\Agents\EmailTemplateGeneratorAgent;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class EmailTemplateService
{
    public function __construct(private AiSettingsResolver $aiSettings = new AiSettingsResolver) {}

    public function list(array $filters): LengthAwarePaginator
    {
        $query = EmailTemplate::with('creator:id,name')
            ->orderBy('created_at', 'desc');

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('subject', 'like', "%{$term}%");
            });
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function create(array $data, User $user): EmailTemplate
    {
        $data['created_by'] = $user->id;

        return EmailTemplate::create($data);
    }

    public function update(EmailTemplate $template, array $data): EmailTemplate
    {
        $template->update($data);

        return $template->fresh();
    }

    public function delete(EmailTemplate $template): void
    {
        $template->delete();
    }

    public function preview(EmailTemplate $template, array $data = []): array
    {
        $sampleData = array_merge([
            'contact_name' => 'John Doe',
            'contact_email' => 'john@example.com',
            'company_name' => 'Acme Corp',
            'account_manager_name' => 'Manager Name',
            'deal_value' => '10000.00',
        ], $data);

        $body = $template->body;
        $subject = $template->subject;

        foreach ($sampleData as $key => $value) {
            $body = str_replace("{{{$key}}}", $value, $body);
            $subject = str_replace("{{{$key}}}", $value, $subject);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    public function generateWithAi(array $data): array
    {
        $language = $data['language'] ?? 'en';
        $languageName = $language === 'ar' ? 'Arabic' : 'English';
        $tone = $data['tone'] ?? 'professional';
        $audience = $data['audience'] ?? 'our customers';
        $context = $data['context'] ?? '';

        $agent = EmailTemplateGeneratorAgent::make(tone: $tone, languageName: $languageName);
        $ai = $this->aiSettings->applyToConfig();

        try {
            $response = $agent->prompt(
                'Write a '.$tone.' email template about: '.$data['topic']
                .'\nTarget audience: '.$audience
                .($context ? '\nAdditional context: '.$context : ''),
                model: $ai['model'],
            );
        } catch (\Throwable $e) {
            logger()->error('AI email generation failed', [
                'message' => $e->getMessage(),
                'topic' => $data['topic'] ?? null,
            ]);

            throw ValidationException::withMessages([
                'ai' => __('messages.ai_generation_failed'),
            ]);
        }

        return $this->parseAiResponse($response->text);
    }

    private function parseAiResponse(string $text): array
    {
        $subject = '';
        $body = '';

        if (preg_match('/SUBJECT:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
            $subject = trim($matches[1]);
        }

        if (preg_match('/BODY:\s*(.+)/is', $text, $matches)) {
            $body = trim($matches[1]);
        }

        if (empty($body)) {
            $body = trim($text);
        }

        preg_match_all('/\{\{(\w+)\}\}/', $subject.' '.$body, $placeholderMatches);

        return [
            'subject' => $subject,
            'body' => $body,
            'placeholders' => array_values(array_unique($placeholderMatches[1])),
        ];
    }
}
