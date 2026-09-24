<?php

declare(strict_types=1);

namespace SqlFormatter\Layout;

use SqlFormatter\FormatOptions;
use SqlFormatter\Style;

/**
 * Whitespace and alignment decisions for one nested block.
 *
 * @visibility SqlFormatter
 */
final class Policy
{
    /**
     * Uses a width calculated from the headers in this block only.
     */
    public function __construct(public readonly FormatOptions $options, public readonly int $base, public readonly int $width)
    {
    }

    /**
     * Starts a line at a column, or separates compact tokens.
     */
    public function line(int $indent): string
    {
        return "\n" . str_repeat(' ', $indent);
    }

    /**
     * Aligns the beginning of a header within this block.
     */
    public function beforeHeader(int $length): string
    {
        return $this->line($this->base + ($this->options->style === Style::River ? max(0, $this->width - $length) : 0));
    }

    /**
     * Places the body below or beside its header.
     */
    public function afterHeader(int $length): string
    {
        return match ($this->options->style) {
            Style::Expanded => $this->line($this->bodyIndent()),
            Style::Tabular => str_repeat(' ', max(1, $this->width - $length + 1)),
            Style::Compact, Style::River => ' ',
        };
    }

    /**
     * Answers the column where list items continue.
     */
    public function bodyIndent(): int
    {
        return $this->base + ($this->options->style === Style::Expanded ? $this->options->indentWidth : $this->width + 1);
    }
}
