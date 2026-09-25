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
     * One labelled chip, tinted by the tone named in the class.
     *
     * @param string $role The role classes the chip carries, such as `tone-blue` or `chip-ghost`
     */
    public function chip(string $text, string $role = '', string $title = ''): string
    {
        return '<span class="chip' . ($role === '' ? '' : ' ' . $this->escape($role)) . '"'
            . ($title === '' ? '' : ' title="' . $this->escape($title) . '"')
            . '>' . $this->escape($text) . '</span>';
    }

    /**
     * One chip that is also a link.
     */
    public function chipLink(string $text, string $href, string $role = '', string $title = ''): string
    {
        return '<a class="chip' . ($role === '' ? '' : ' ' . $this->escape($role)) . '" href="' . $this->escape($href) . '"'
            . ($title === '' ? '' : ' title="' . $this->escape($title) . '"')
            . '>' . $this->escape($text) . '</a>';
    }

    /**
     * One chip that is a link and carries how many it stands for.
     */
    public function chipCount(string $text, string $href, int $count, string $role = ''): string
    {
        return '<a class="chip' . ($role === '' ? '' : ' ' . $this->escape($role)) . '" href="' . $this->escape($href) . '">'
            . $this->escape($text) . '<span class="facet-count">' . $this->number($count) . '</span></a>';
    }

    /**
     * One link.
     */
    public function link(string $text, string $href, string $class = ''): string
    {
        return '<a' . ($class === '' ? '' : ' class="' . $this->escape($class) . '"') . ' href="' . $this->escape($href) . '">'
            . $this->escape($text) . '</a>';
    }

    /**
     * A name with every gap in it marked, the way a gap in a statement is.
     */
    public function marked(string $name): string
    {
        return str_replace(
            '{$}',
            '<span class="hole tone-warn" title="A part of this name the analysis could not pin down">{$}</span>',
            $this->escape($name),
        );
    }

    /**
     * A count written the way a reader scans it.
     */
    public function number(int $value): string
    {
        return number_format($value);
    }

    /**
     * The noun a count is written with, singular or plural.
     */
    public function noun(int $value, string $noun): string
    {
        return $noun . ($value === 1 ? '' : 's');
    }

    /**
     * A count with the noun it counts, singular or plural.
     */
    public function plural(int $value, string $noun): string
    {
        return $this->number($value) . ' ' . $this->noun($value, $noun);
    }

    /**
     * A share of a total, as a percentage with no decimals.
     */
    public function percent(int $value, int $total): string
    {
        return $total === 0 ? '0%' : (string) ((int) round($value * 100 / $total)) . '%';
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
     * Text with every run of whitespace written as one space.
     */
    public function collapse(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Text shortened to fit, with an ellipsis where it was cut.
     */
    public function truncate(string $text, int $length): string
    {
        $collapsed = $this->collapse($text);

        return strlen($collapsed) <= $length ? $collapsed : substr($collapsed, 0, $length - 1) . '…';
    }

    /**
     * A count beside a label, the way a heading carries one.
     */
    public function count(int $value, string $noun = ''): string
    {
        return '<span class="count">' . $this->escape($noun === '' ? $this->number($value) : $this->plural($value, $noun)) . '</span>';
    }
}
