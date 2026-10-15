<?php

namespace App\Mail;

use App\Models\CampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CampaignRecipient $recipient) {}

    public function envelope(): Envelope
    {
        $subject = $this->resolveSubject();

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->resolveBody(),
        );
    }

    private function resolveSubject(): string
    {
        return $this->replacePlaceholders(
            $this->recipient->campaign->template->subject
        );
    }

    private function resolveBody(): string
    {
        return $this->replacePlaceholders(
            $this->recipient->campaign->template->body
        );
    }

    private function replacePlaceholders(string $text): string
    {
        $customer = $this->recipient->customer;
        $manager = $customer->accountManager;
        $contacts = $customer->contacts ?? [];
        $primaryContact = collect($contacts)->first(fn (array $contact) => $contact['is_primary'] ?? false)
            ?? collect($contacts)->first();
        $contactName = $customer->contact_name
            ?: trim(($primaryContact['first_name'] ?? '').' '.($primaryContact['last_name'] ?? ''))
            ?: ($customer->type === 'individual' ? $customer->full_name : $customer->company_name)
            ?: $customer->company_name
            ?: '';

        $replacements = [
            'contact_name' => $contactName,
            'contact_email' => $this->recipient->email ?: ($customer->email ?? $customer->contact_email ?? $primaryContact['email'] ?? ''),
            'contact_phone' => $customer->phone ?: ($customer->contact_phone ?? $primaryContact['phone'] ?? ''),
            'company_name' => $customer->company_name ?? '',
            'account_manager_name' => $manager?->name ?? '',
            'deal_value' => (string) $customer->opportunities()->where('stage', 'won')->sum('amount'),
        ];

        foreach ($replacements as $key => $value) {
            $text = str_replace("{{{$key}}}", $value, $text);
        }

        return $text;
    }
}
