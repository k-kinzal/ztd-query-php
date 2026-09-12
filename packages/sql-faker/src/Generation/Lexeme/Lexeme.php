<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Lexeme;

use SqlFaker\Generation\Token\TerminalOccurrence;

/**
 * Concrete text and its lexical use, without a decision about surrounding spaces.
 */
final class Lexeme
{
    /**
     * Keeps spelling, lexical category and occurrence provenance together as immutable output.
     */
    public function __construct(
        public readonly string $text,
        public readonly string $kind,
        public readonly TerminalOccurrence $origin,
        public readonly string $definition,
        public readonly ?string $phrase = null,
    ) {
    }
}
