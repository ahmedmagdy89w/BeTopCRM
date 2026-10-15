<?php

namespace App\Services;

use ArPHP\I18N\Arabic;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use DOMDocument;
use DOMXPath;

/**
 * Render PDF views with Arabic glyph shaping and Cairo typography.
 *
 * dompdf cannot join Arabic letters or order them right-to-left, so Arabic
 * text (e.g. ضريبة) renders as isolated reversed letters (ةبيرض). This
 * wrapper shapes only the text nodes of the rendered HTML into visual-order
 * presentation forms (Cairo and DejaVu Sans cover them) and leaves
 * everything else byte-identical.
 */
class ArabicPdfService
{
    public static function loadView(string $view, array $data = []): DomPdf
    {
        return Pdf::loadHTML(self::renderShapedHtml($view, $data));
    }

    public static function renderShapedHtml(string $view, array $data = []): string
    {
        $html = self::injectPdfTypography(view($view, $data)->render());

        if (self::containsArabic($html)) {
            $html = self::shapeHtmlTextNodes($html);
        }

        return $html;
    }

    public static function containsArabic(?string $text): bool
    {
        if ($text === null || $text === '') {
            return false;
        }

        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    public static function shapeText(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        if (! self::containsArabic($text)) {
            return $text;
        }

        // Split into Arabic runs, whitespace runs, and everything else (numbers,
        // Latin, punctuation). utf8Glyphs() reverses digit separators
        // (1,250.00 becomes 00.250,1), so numbers must never go through it —
        // only pure Arabic runs do. Whitespace stays its own run so gaps land
        // between words after visual reordering instead of gluing to a run.
        $parts = preg_split(
            '/(\s+|[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]+)/u',
            $text,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        if ($parts === false) {
            return $text;
        }

        $arabic = new Arabic;
        $shaped = array_map(
            fn (string $part): string => self::containsArabic($part)
                // Keep Western digits (prices, quantities) and avoid mid-cell breaks.
                ? $arabic->utf8Glyphs($part, 200, false)
                : $part,
            $parts
        );

        // Visual order for dompdf (which has no bidi engine): reverse RTL lines.
        return self::startsWithArabic($text) ? implode('', array_reverse($shaped)) : implode('', $shaped);
    }

    private static function startsWithArabic(string $text): bool
    {
        return (bool) preg_match('/^[\s\d\p{P}]*[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    private static function shapeHtmlTextNodes(string $html): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query('//body//text()[not(ancestor::script) and not(ancestor::style)]');

        if ($nodes === false) {
            return $html;
        }

        foreach ($nodes as $node) {
            $text = $node->nodeValue;

            if (! is_string($text) || ! self::containsArabic($text)) {
                continue;
            }

            $node->nodeValue = self::shapeText($text);
        }

        $shaped = $dom->saveHTML();

        return $shaped === false ? $html : $shaped;
    }

    /**
     * Register the Cairo typeface (regular + bold, shipped in public/fonts)
     * and make it the PDF body font, keeping DejaVu Sans as fallback.
     * Injected here so every PDF view is covered from the single choke point.
     */
    private static function injectPdfTypography(string $html): string
    {
        $fonts = str_replace('\\', '/', public_path('fonts'));
        $css = '<style>'
            ."@font-face{font-family:'Cairo';font-style:normal;font-weight:400;src:url('{$fonts}/Cairo-Regular.ttf');}"
            ."@font-face{font-family:'Cairo';font-style:normal;font-weight:700;src:url('{$fonts}/Cairo-Bold.ttf');}"
            ."body{font-family:'Cairo','DejaVu Sans',sans-serif;}"
            .'</style>';

        if (stripos($html, '</head>') !== false) {
            $injected = preg_replace('/<\/head>/i', $css.'</head>', $html, 1);

            return is_string($injected) ? $injected : $html;
        }

        return $css.$html;
    }
}
