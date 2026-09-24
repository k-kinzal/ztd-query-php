<?php

declare(strict_types=1);

namespace SqlFormatter\Layout;

use SqlFormatter\Syntax\Document;

/**
 * Measures and emits headers without looking inside nested query blocks.
 *
 * @visibility SqlFormatter
 */
final class Headers
{
    /**
     * Reads annotations from the concrete syntax tree.
     */
    public function __construct(private readonly Document $document)
    {
    }

    /**
     * Finds the widest header at this parenthesis level.
     */
    public function width(int $start, int $end): int
    {
        $width = 0;
        for ($index = $start; $index <= $end; $index++) {
            $close = $this->document->pairs[$index] ?? $this->document->casePairs[$index] ?? null;
            if ($close !== null) {
                $index = $close;
                continue;
            }
            $last = $this->document->clauses[$index] ?? null;
            if ($last !== null) {
                $width = max($width, $this->length($index, $last));
            }
        }
        return $width;
    }

    /**
     * Measures a header with one space between its words.
     */
    public function length(int $start, int $end): int
    {
        $width = $end - $start;
        for ($index = $start; $index <= $end; $index++) {
            $width += strlen($this->document->tokens[$index]->text);
        }
        return $width;
    }

    /**
     * Emits a complete multiword header, keeping interleaved comments.
     */
    public function write(Writer $writer, int $start, int $end, Policy $policy): void
    {
        for ($index = $start; $index <= $end; $index++) {
            $writer->token($this->document->tokens[$index], $index === $start ? $policy->beforeHeader($this->length($start, $end)) : ' ');
        }
    }
}
