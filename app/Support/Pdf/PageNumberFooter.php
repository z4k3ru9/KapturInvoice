<?php

namespace App\Support\Pdf;

use Barryvdh\DomPDF\PDF;
use Dompdf\Canvas;
use Dompdf\FontMetrics;

/**
 * Shared "Page X of Y" running footer for every printed PDF (Phase 8 —
 * PDF pagination footer).
 *
 * CSS Paged Media `@page { @bottom-right { content: counter(page) ... } }`
 * margin boxes are NOT implemented by the installed dompdf (v3.1.6):
 * confirmed by reading `vendor/dompdf/dompdf/src/Css/Stylesheet.php`'s
 * `_parse_css()` "page" case, which only recognizes the bare `@page`
 * selector (or `:left`/`:right`/`:odd`/`:even`/`:first`) and has no
 * handling at all for nested `@top-*`/`@bottom-*` margin-box at-rules —
 * and confirmed empirically: a real 4-page render with that CSS produced
 * no visible footer text on any page. See
 * resources/views/pdf/partials/page-footer.blade.php (the CSS half of
 * this feature — it only reserves the bottom margin this class draws
 * into).
 *
 * dompdf's actually-working mechanism for running page-number text is
 * `Canvas::page_script()`, which draws directly onto each page's canvas
 * once the final page count is known. That only happens after
 * `Dompdf::render()` — so `apply()` must run after `Pdf::loadView()` and
 * before `->stream()`/`->download()`/`->output()`/`->save()`, and it
 * forces the render itself so the page count is available (the wrapper's
 * own `output()`/`stream()`/`download()` skip re-rendering once
 * `render()` has already been called).
 */
class PageNumberFooter
{
    private const FONT_SIZE = 9.0;

    /** rgb(107, 114, 128) — the same `.muted` gray every template's own CSS uses. */
    private const COLOR = [0.4196, 0.4471, 0.5020];

    private const MARGIN_RIGHT_PT = 28.0;

    private const MARGIN_BOTTOM_PT = 28.0;

    /**
     * Renders the PDF (if not already rendered) and draws the page-number
     * footer onto every page. Returns the same instance for chaining into
     * ->stream()/->download()/->output()/->save().
     */
    public static function apply(PDF $pdf): PDF
    {
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $fontMetrics = $pdf->getDomPDF()->getFontMetrics();
        $font = $fontMetrics->getFont('helvetica', 'normal');

        $pageLabel = __('documents.page');
        $ofLabel = __('documents.of');

        $canvas->page_script(
            function (int $pageNumber, int $pageCount, Canvas $canvas, FontMetrics $fontMetrics) use ($font, $pageLabel, $ofLabel) {
                $text = "{$pageLabel} {$pageNumber} {$ofLabel} {$pageCount}";
                $width = $fontMetrics->getTextWidth($text, $font, self::FONT_SIZE);

                $x = $canvas->get_width() - self::MARGIN_RIGHT_PT - $width;
                $y = $canvas->get_height() - self::MARGIN_BOTTOM_PT;

                $canvas->text($x, $y, $text, $font, self::FONT_SIZE, self::COLOR);
            }
        );

        return $pdf;
    }
}
