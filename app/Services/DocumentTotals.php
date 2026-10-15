<?php

namespace App\Services;

/**
 * Line-based totals shared by quotations and invoices.
 *
 * Each line: gross = qty × price. When a discount percent is present it
 * wins and the amount is derived from it; otherwise the given amount is
 * used, capped at gross. Tax applies to (gross − discount) using the
 * snapshotted rate.
 */
final class DocumentTotals
{
    /**
     * @return array{product_id: mixed, product_name: string, sku: mixed, quantity: int, unit_price: float, subtotal: float, discount_percent: ?float, discount_amount: float, tax_id: mixed, tax_name: ?string, tax_percent: float, tax_amount: float, notes: mixed}
     */
    public static function line(
        mixed $productId,
        string $productName,
        mixed $sku,
        int $quantity,
        float $unitPrice,
        float $discountAmount,
        mixed $taxId,
        ?string $taxName,
        float $taxPercent,
        mixed $notes,
        ?float $discountPercent = null,
    ): array {
        $gross = $quantity * $unitPrice;
        $discount = $discountPercent !== null && $discountPercent > 0
            ? round($gross * (min($discountPercent, 100) / 100), 2)
            : min(max($discountAmount, 0), $gross);
        $taxable = $gross - $discount;
        $taxAmount = round($taxable * ($taxPercent / 100), 2);

        return [
            'product_id' => $productId,
            'product_name' => $productName,
            'sku' => $sku,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $gross,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discount,
            'tax_id' => $taxId,
            'tax_name' => $taxName,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<int, array{subtotal: float, discount_amount: float, tax_amount: float}>  $lines
     * @return array{subtotal: float, discount_amount: float, tax_amount: float, total: float}
     */
    public static function headers(array $lines): array
    {
        $subtotal = (float) array_sum(array_column($lines, 'subtotal'));
        $discount = (float) array_sum(array_column($lines, 'discount_amount'));
        $tax = (float) array_sum(array_column($lines, 'tax_amount'));

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => $subtotal - $discount + $tax,
        ];
    }
}
