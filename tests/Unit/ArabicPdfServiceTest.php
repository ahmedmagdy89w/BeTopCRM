<?php

use App\Services\ArabicPdfService;

test('detects arabic text', function () {
    expect(ArabicPdfService::containsArabic('ضريبة'))->toBeTrue()
        ->and(ArabicPdfService::containsArabic('Hello'))->toBeFalse()
        ->and(ArabicPdfService::containsArabic(''))->toBeFalse()
        ->and(ArabicPdfService::containsArabic(null))->toBeFalse();
});

test('shapes arabic into visual-order presentation forms', function () {
    $shaped = ArabicPdfService::shapeText('ضريبة');

    expect($shaped)->not->toBe('ضريبة')
        // Output must use Arabic Presentation Forms (FE70–FEFF), which DejaVu Sans covers.
        ->and($shaped)->toMatch('/[\x{FE70}-\x{FEFF}]/u')
        // No unshaped Arabic letters may remain.
        ->and($shaped)->not->toMatch('/[\x{0600}-\x{06FF}]/u');
});

test('leaves latin text untouched', function () {
    expect(ArabicPdfService::shapeText('Hello World'))->toBe('Hello World')
        ->and(ArabicPdfService::shapeText(''))->toBe('')
        ->and(ArabicPdfService::shapeText(null))->toBe('');
});

test('keeps western digits in mixed strings', function () {
    $shaped = ArabicPdfService::shapeText('المبلغ 1,250.00');

    expect($shaped)->toContain('1,250.00')
        ->and($shaped)->not->toMatch('/[\x{0660}-\x{0669}]/u');
});

test('orders rtl lines visually for dompdf', function () {
    $shaped = ArabicPdfService::shapeText('المبلغ 1,250.00');

    // Number stays left of the shaped Arabic label in visual order.
    expect($shaped)->toStartWith('1,250.00 ');
});

test('keeps ltr lines in logical order', function () {
    $shaped = ArabicPdfService::shapeText('Total الإجمالي');

    expect($shaped)->toStartWith('Total ');
});
