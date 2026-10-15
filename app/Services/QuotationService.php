<?php

namespace App\Services;

use App\Filters\QuotationFilter;
use App\Models\Opportunity;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $query = (new QuotationFilter($filters))->apply(
            Quotation::with(['customer', 'opportunity'])
        );

        $allowedSort = ['created_at', 'quotation_number', 'valid_until', 'total', 'status'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSort) ? $filters['sort_by'] : 'created_at';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)
            ->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 100)));
    }

    public function create(Opportunity $opportunity, array $data, User $user): Quotation
    {
        if (! $opportunity->isOpen()) {
            throw ValidationException::withMessages([
                'opportunity' => [__('messages.quotation_open_opportunity')],
            ]);
        }

        $products = $opportunity->products()->with('tax')->get();

        if ($products->isEmpty()) {
            throw ValidationException::withMessages([
                'products' => ['Add at least one product before creating a quotation.'],
            ]);
        }

        $items = $products->map(fn ($product): array => DocumentTotals::line(
            productId: $product->id,
            productName: $product->name,
            sku: $product->sku,
            quantity: (int) ($product->pivot->quantity ?? 1),
            unitPrice: (float) ($product->pivot->unit_price ?? $product->unit_price),
            discountAmount: (float) ($product->pivot->discount_amount ?? 0),
            discountPercent: isset($product->pivot->discount_percent) ? (float) $product->pivot->discount_percent : null,
            taxId: $product->tax?->id,
            taxName: $product->tax?->name,
            taxPercent: (float) ($product->tax?->percent ?? 0),
            notes: $product->pivot->notes,
        ))->all();
        $headers = DocumentTotals::headers($items);

        return DB::transaction(function () use ($opportunity, $user, $data, $items, $headers): Quotation {
            $quotation = Quotation::create([
                'opportunity_id' => $opportunity->id,
                'customer_id' => $opportunity->customer_id,
                'created_by' => $user->id,
                'quotation_number' => 'pending-'.uniqid(),
                'status' => 'draft',
                'currency' => $opportunity->currency,
                'subtotal' => $headers['subtotal'],
                'tax_percent' => 0,
                'tax_amount' => $headers['tax_amount'],
                'discount_amount' => $headers['discount_amount'],
                'total' => $headers['total'],
                'valid_until' => $data['valid_until'] ?? now()->addDays(30)->toDateString(),
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $quotation->update([
                'quotation_number' => 'QT-'.now()->format('Y').'-'.str_pad((string) $quotation->id, 4, '0', STR_PAD_LEFT),
            ]);
            $quotation->items()->createMany($items);

            return $quotation->load(['items', 'customer', 'opportunity']);
        });
    }

    public function send(Quotation $quotation, User $user): Quotation
    {
        if ($quotation->status === 'sent') {
            return $quotation;
        }

        $email = $quotation->customer->email ?: $quotation->customer->contact_email;

        if (! $email) {
            throw ValidationException::withMessages([
                'customer' => ['The customer does not have an email address.'],
            ]);
        }

        $quotation->update(['status' => 'sent', 'sent_at' => now(), 'sent_by' => $user->id]);

        return $quotation->fresh(['items', 'customer', 'opportunity']);
    }
}
