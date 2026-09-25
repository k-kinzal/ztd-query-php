<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Layout;

use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Syntax\Document;

/**
 * Renders a nested token range with independent clause alignment.
 *
 * @visibility SqlFormatter
 */
final class Block
{
    /**
     * Shares the immutable options and the document output buffer.
     */
    public function __construct(private readonly Document $document, private readonly Writer $writer, private readonly FormatOptions $options)
    {
    }

    /**
     * Renders a block; nested elements call back with their own indentation scope.
     */
    public function render(int $start, int $end, int $base): void
    {
        $headers = new Headers($this->document);
        $policy = new Policy($this->options, $base, $headers->width($start, $end));
        $elements = new Elements($this->document, $this->writer, $this, $policy);
        $indent = $base;
        $pending = $start === 0 ? '' : $policy->line($base);
        for ($index = $start; $index <= $end; $index++) {
            $header = $this->document->clauses[$index] ?? null;
            if ($header !== null) {
                $headers->write($this->writer, $index, $header, $policy);
                $indent = $policy->bodyIndent();
                $pending = $policy->afterHeader($headers->length($index, $header));
                $index = $header;
                continue;
            }
            $pending = $elements->write($index, $end, $indent, $pending);
        }
    }
}
