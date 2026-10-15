<?php

namespace App\Services;

use App\Filters\InvoiceFilter;
use App\Models\Invoice;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $query = (new InvoiceFilter($filters))->apply(
            Invoice::with(['customer', 'opportunity'])
        );

        $allowedSort = ['created_at', 'invoice_number', 'issued_at', 'total', 'status'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSort) ? $filters['sort_by'] : 'created_at';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)
            ->paginate(max(1, min((int) ($filters['per_page'] ?? 15), 100)));
    }

    public function create(Opportunity $opportunity, User $user): Invoice
    {
        if ($opportunity->stage !== 'won') {
            throw ValidationException::withMessages([
                'opportunity' => ['An invoice can only be created for a won opportunity.'],
            ]);
        }

        if ($opportunity->invoice()->exists()) {
            throw ValidationException::withMessages([
                'invoice' => ['This opportunity already has an invoice.'],
            ]);
        }

        $quotation = $opportunity->quotations()->where('status', 'sent')->latest()->first()
            ?? $opportunity->quotations()->latest()->first();
        $quotationItems = $quotation?->items()->get();

        if ($quotationItems !== null && $quotationItems->isNotEmpty()) {
            // Copy line snapshots (discount + tax) from the quotation.
            $items = $quotationItems->map(fn ($item): array => DocumentTotals::line(
                productId: $item->product_id,
                productName: $item->product_name,
                sku: $item->sku,
                quantity: (int) $item->quantity,
                unitPrice: (float) $item->unit_price,
                discountAmount: (float) ($item->discount_amount ?? 0),
                discountPercent: isset($item->discount_percent) ? (float) $item->discount_percent : null,
                taxId: $item->tax_id,
                taxName: $item->tax_name,
                taxPercent: (float) ($item->tax_percent ?? 0),
                notes: $item->notes,
            ))->all();
        } else {
            $items = $opportunity->products()->with('tax')->get()->map(fn ($product): array => DocumentTotals::line(
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
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'products' => ['Add products before creating an invoice.'],
            ]);
        }

        $headers = DocumentTotals::headers($items);

        return DB::transaction(function () use ($opportunity, $quotation, $user, $items, $headers): Invoice {
            $invoice = Invoice::create([
                'opportunity_id' => $opportunity->id,
                'customer_id' => $opportunity->customer_id,
                'quotation_id' => $quotation?->id,
                'created_by' => $user->id,
                'invoice_number' => 'pending-'.uniqid(),
                'status' => 'issued',
                'currency' => $quotation?->currency ?? $opportunity->currency,
                'subtotal' => $headers['subtotal'],
                'tax_percent' => 0,
                'tax_amount' => $headers['tax_amount'],
                'discount_amount' => $headers['discount_amount'],
                'total' => $headers['total'],
                'issued_at' => now()->toDateString(),
            ]);

            $invoice->update([
                'invoice_number' => 'INV-'.now()->format('Y').'-'.str_pad((string) $invoice->id, 4, '0', STR_PAD_LEFT),
            ]);
            $invoice->items()->createMany($items);

            return $invoice->load(['items', 'customer', 'opportunity', 'quotation']);
        });
    }
}
