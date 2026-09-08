<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Output;

use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;

/**
 * Immutable right-hand output with the condition still owed by its left neighbor.
 */
final class ResolvedOutput
{
    /**
     * @param list<OutputPart> $parts
     * @param list<LexemeSequence> $candidates Selected candidates, including non-output markers
     */
    public function __construct(
        public readonly array $parts = [],
        public readonly ?SpacingConstraint $left = null,
        public readonly array $candidates = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function pieces(): array
    {
        $pieces = [];
        foreach ($this->parts as $part) {
            $pieces[] = $part->lexeme->text;
            $pieces[] = $part->separator;
        }
        return $pieces;
    }
}
