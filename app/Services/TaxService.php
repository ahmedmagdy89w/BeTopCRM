<?php

namespace App\Services;

use App\Models\Tax;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class TaxService
{
    public function list(bool $activeOnly = false): Collection
    {
        return Tax::query()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Tax
    {
        return Tax::create($data);
    }

    public function update(Tax $tax, array $data): Tax
    {
        $tax->update($data);

        return $tax->fresh();
    }

    public function delete(Tax $tax): void
    {
        if ($tax->products()->exists()) {
            throw ValidationException::withMessages([
                'tax' => [__('messages.tax_delete_has_products')],
            ]);
        }

        $tax->delete();
    }
}
