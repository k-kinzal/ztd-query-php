<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Output;

use SqlFaker\Grammar\Generation\Lexeme\Lexeme;

/**
 * A chosen lexeme and its resolved right boundary.
 */
final class OutputPart
{
    /**
     * @param list<string> $spacingRules
     */
    public function __construct(
        public readonly Lexeme $lexeme,
        public readonly string $separator,
        public readonly string $candidate,
        public readonly array $spacingRules = [],
    ) {
    }
}
