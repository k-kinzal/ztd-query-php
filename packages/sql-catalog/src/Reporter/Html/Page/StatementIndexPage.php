<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html\Page;

use SqlCatalog\Reporter\Html\HtmlText;
use SqlCatalog\Reporter\Html\ReportSite;
use SqlCatalog\Reporter\Html\StatementList;

/**
 * Every statement, to be narrowed down from any direction.
 *
 * The other listings each take one route to a statement. This one takes
 * none, so that a reader who wants the statements of one kind, or of one
 * resolution, or those a namespace issues on one table, can narrow the whole
 * catalog by those facts at once. Every count elsewhere in the report that
 * is a link leads here with the narrowing already applied.
 *
 * @visibility root
 */
final class StatementIndexPage
{
    private HtmlText $text;

    private StatementList $list;

    /**
     * Wires the page to the rendering it is written with.
     */
    public function __construct(?HtmlText $text = null, ?StatementList $list = null)
    {
        $this->text = $text ?? new HtmlText();
        $this->list = $list ?? new StatementList($this->text);
    }

    /**
     * The body of the listing.
     */
    public function render(ReportSite $site): string
    {
        $entries = $site->index()->entries();

        return '<h1>Statements' . $this->text->count(count($entries), 'statement') . '</h1>'
            . '<p class="lede">Every statement the source can issue, in the order it is written. '
            . 'Narrow the listing by what a statement does, how far the analysis got with it, or any text in it.</p>'
            . '<div data-narrowable>'
            . $this->list->facets($entries)
            . '<div class="active-filters" hidden></div>'
            . $this->list->rows($site, ReportSite::STATEMENTS, $entries)
            . '</div>';
    }
}
