<?php

declare(strict_types=1);

namespace SqlCatalog\Reporter\Html;

/**
 * The text primitives every page of the report is written with.
 *
 * @visibility root
 */
final class HtmlText
{
    /**
     * Text made safe to place inside the document.
     */
    public function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * One labelled chip, tinted by the role named in the class.
     *
     * @param string $role The role classes the chip carries, such as `k-select` or `s-ok`
     */
    public function chip(string $text, string $role = '', string $title = ''): string
    {
        return '<span class="chip' . ($role === '' ? '' : ' ' . $this->escape($role)) . '"'
            . ($title === '' ? '' : ' title="' . $this->escape($title) . '"')
            . '>' . $this->escape($text) . '</span>';
    }

    /**
     * A count written the way a reader scans it.
     */
    public function number(int $value): string
    {
        return number_format($value);
    }

    /**
     * A count with the noun it counts, singular or plural.
     */
    public function plural(int $value, string $noun): string
    {
        return $this->number($value) . ' ' . $noun . ($value === 1 ? '' : 's');
    }

    /**
     * A share of a total, as a percentage with no decimals.
     */
    public function percent(int $value, int $total): string
    {
        return $total === 0 ? '0%' : (string) ((int) round($value * 100 / $total)) . '%';
    }

    /**
     * A bar showing a share of a total.
     *
     * @param string $role The role class the fill is tinted by, such as `bar-ok`
     */
    public function bar(int $value, int $total, string $role = ''): string
    {
        return '<span class="bar' . ($role === '' ? '' : ' ' . $this->escape($role)) . '">'
            . '<span style="--w:' . $this->escape($this->percent($value, $total)) . '"></span></span>';
    }

    /**
     * A string turned into something that can be written as an identifier.
     */
    public function slug(string $text): string
    {
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $text));

        return trim($slug, '-');
    }

    /**
     * Text shortened to fit, with an ellipsis where it was cut.
     */
    public function truncate(string $text, int $length): string
    {
        $collapsed = trim((string) preg_replace('/\s+/', ' ', $text));

        return strlen($collapsed) <= $length ? $collapsed : substr($collapsed, 0, $length - 1) . '…';
    }
}
