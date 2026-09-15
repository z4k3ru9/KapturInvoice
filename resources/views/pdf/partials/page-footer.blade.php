{{--
    Shared pagination footer for every printed PDF (Phase 8 — PDF
    pagination footer).

    IMPORTANT: this partial only reserves the bottom margin the footer
    text is drawn into — it does NOT draw the "Page X of Y" text itself.

    CSS Paged Media `@page { @bottom-right { content: counter(page) ... } }`
    margin boxes are NOT implemented by the installed dompdf (v3.1.6):
    confirmed by reading vendor/dompdf/dompdf/src/Css/Stylesheet.php's
    `_parse_css()` "page" case, which only recognizes the `@page`
    selector itself (bare / `:left` / `:right` / `:odd` / `:even` /
    `:first`) and has no handling at all for nested `@top-*`/`@bottom-*`
    margin-box at-rules — and confirmed empirically: a real 4-page
    render with that CSS produced no visible footer text on any page,
    even though the plain `margin-bottom` below (a supported `@page`
    property) was respected.

    dompdf's actually-working mechanism for running page-number text is
    the Canvas::page_script()/page_text() PHP API (draws directly to the
    canvas once the final page count is known, after render()) — see
    App\Support\Pdf\PageNumberFooter, applied from every PDF controller/
    mailer that calls Pdf::loadView(), right before ->stream()/
    ->download()/->output()/->save(). This partial's only remaining job
    is the CSS side of that: reserving vertical space at the bottom of
    every page so body content never overlaps the footer text.

    @include this partial INSIDE each template's existing <style>...</style>
    block — it emits plain CSS, not its own <style> tag, so every template
    keeps exactly one style block.
--}}
@page {
    margin-bottom: 70px;
}
