<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

/**
 * Captures one symbol within a snapshotted grammar alternative.
 */
final class GrammarSymbolSnapshot
{
    public function __construct(
        public readonly string $value,
        public readonly bool $isNonTerminal,
    ) {
    }
}
