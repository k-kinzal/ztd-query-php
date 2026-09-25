<?php

declare(strict_types=1);

namespace SqlCatalog\Catalog;

use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;

/**
 * One piece of a catalogued statement: a run of text that is known, or a gap.
 *
 * A statement the analyzer could not pin down is not a string with a marker in
 * it. It is a shape, and the gaps in that shape are claims about the analysis:
 * this much is the statement, and this much is a value that would be spliced
 * into it. Reporting the two apart is what lets a reader see which is which
 * instead of reading `{$}` as something the program writes.
 *
 * @visibility root
 */
final class StatementPart
{
    /**
     * @param string $text The characters of a known run, empty for a gap
     * @param bool $isGap Whether this is a gap rather than a run of known text
     * @param string $origin Where the value filling a gap comes from, empty for a known run
     * @param string $reason What fills the gap, written for a reader, empty for a known run
     * @param string|null $expression The source expression of a gap, when it is short enough to quote
     * @param string|null $variable The PHP variable read at the use site, when known
     */
    public function __construct(
        public readonly string $text,
        public readonly bool $isGap = false,
        public readonly string $origin = '',
        public readonly string $reason = '',
        public readonly ?string $expression = null,
        public readonly ?string $variable = null,
    ) {
    }

    /**
     * The parts a reconstructed statement is known in, in order.
     *
     * @return list<self>
     */
    public static function of(TextPattern $pattern): array
    {
        $parts = [];
        foreach ($pattern->segments as $segment) {
            if ($segment instanceof LiteralText) {
                $parts[] = new self($segment->text);
                continue;
            }
            $parts[] = $segment instanceof TextHole
                ? new self('', true, $segment->origin->value, $segment->origin->describe(), $segment->expression, $segment->variable)
                : new self($segment->display());
        }

        return $parts;
    }
}
