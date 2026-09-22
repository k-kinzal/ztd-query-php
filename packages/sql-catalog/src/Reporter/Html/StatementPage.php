<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

use SqlCatalog\Catalog\CatalogEntry;

/**
 * One page of statements, listed under the files they are written in.
 *
 * @visibility root
 */
final class StatementPage
{
    /**
     * How many page numbers the pager shows around the one being read.
     */
    public const WINDOW = 2;

    private HtmlText $text;

    private StatementCard $card;

    /**
     * Wires the page to the rendering it is written with.
     */
    public function __construct(?HtmlText $text = null, ?StatementCard $card = null)
    {
        $this->text = $text ?? new HtmlText();
        $this->card = $card ?? new StatementCard($this->text);
    }

    /**
     * The body of one page of statements.
     */
    public function render(ReportSite $site, int $number): string
    {
        $page = $site->pages()[$number - 1] ?? [];
        $held = array_sum(array_map('count', $page));

        $groups = '';
        foreach ($page as $file => $entries) {
            $groups .= $this->group($site, $file, $entries);
        }
        if ($groups === '') {
            $groups = '<p class="lede">No statement was found.</p>';
        }

        return '<h1>Statements <span class="count">page ' . $number . ' of ' . $site->pageCount() . '</span></h1>'
            . '<p class="lede">' . $this->text->escape($this->text->plural($held, 'statement'))
            . ' from ' . $this->text->escape($this->text->plural(count($page), 'file')) . '.</p>'
            . $this->pager($site, $number)
            . $groups
            . $this->pager($site, $number);
    }

    /**
     * Every statement of one file.
     *
     * @param list<CatalogEntry> $entries
     */
    public function group(ReportSite $site, string $file, array $entries): string
    {
        $cards = '';
        foreach ($entries as $entry) {
            $cards .= $this->card->render($entry);
        }
        $anchor = $site->fileAnchor($file);

        return '<section class="file-group" id="' . $this->text->escape($anchor) . '">'
            . '<h2>' . $this->text->escape($file)
            . '<span class="count">' . $this->text->escape($this->text->plural(count($entries), 'statement')) . '</span>'
            . '<a class="anchor" href="#' . $this->text->escape($anchor) . '">#</a></h2>'
            . $cards . '</section>';
    }

    /**
     * The links to the pages either side of this one.
     */
    public function pager(ReportSite $site, int $number): string
    {
        if ($site->pageCount() < 2) {
            return '';
        }
        $prefix = $site->prefixOf($site->pageName($number));
        $links = $this->step($site, $prefix, $number - 1, '‹ Previous');
        foreach ($this->windowOf($number, $site->pageCount()) as $shown) {
            $links .= $shown === null
                ? '<span class="gap">…</span>'
                : ($shown === $number
                    ? '<span class="is-current">' . $shown . '</span>'
                    : '<a href="' . $this->text->escape($prefix . $site->pageName($shown)) . '">' . $shown . '</a>');
        }
        $links .= $this->step($site, $prefix, $number + 1, 'Next ›');

        return '<nav class="pager">' . $links
            . '<span class="pager-note">page ' . $number . ' of ' . $site->pageCount() . '</span></nav>';
    }

    /**
     * One step of the pager, written as a link only when there is a page to step to.
     */
    public function step(ReportSite $site, string $prefix, int $number, string $label): string
    {
        if ($number < 1 || $number > $site->pageCount()) {
            return '<span class="is-disabled">' . $this->text->escape($label) . '</span>';
        }

        return '<a href="' . $this->text->escape($prefix . $site->pageName($number)) . '">'
            . $this->text->escape($label) . '</a>';
    }

    /**
     * The page numbers a pager shows, with null where it skips a run of them.
     *
     * @return list<int|null>
     */
    public function windowOf(int $number, int $count): array
    {
        $shown = [];
        for ($page = 1; $page <= $count; $page++) {
            $near = abs($page - $number) <= self::WINDOW;
            if ($page === 1 || $page === $count || $near) {
                $shown[] = $page;
                continue;
            }
            if ($shown !== [] && $shown[count($shown) - 1] !== null) {
                $shown[] = null;
            }
        }

        return $shown;
    }
}
