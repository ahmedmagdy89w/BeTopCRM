<?php

namespace App\Mail;

use App\Models\Quotation;
use App\Services\ArabicPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class QuotationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quotation $quotation,
        public array $document,
    ) {
        // Queue workers boot with the default locale, so capture the
        // sender's locale now and re-apply it when rendering.
        // (Uses Mailable::$locale; must stay untyped.)
        $this->locale = App::getLocale();
    }

    public function envelope(): Envelope
    {
        App::setLocale($this->locale);

        return new Envelope(subject: __('messages.quotation_email_subject', [
            'number' => $this->quotation->quotation_number,
        ]));
    }

    public function content(): Content
    {
        App::setLocale($this->locale);

        return new Content(
            view: 'emails.quotation',
            with: ['document' => $this->document],
        );
    }

    public function attachments(): array
    {
        App::setLocale($this->locale);

        return [
            Attachment::fromData(
                fn (): string => ArabicPdfService::loadView('quotations.pdf', [
                    'quotation' => $this->quotation,
                    'document' => $this->document,
                ])->output(),
                $this->quotation->quotation_number.'.pdf',
            )->withMime('application/pdf'),
        ];
    }
}
