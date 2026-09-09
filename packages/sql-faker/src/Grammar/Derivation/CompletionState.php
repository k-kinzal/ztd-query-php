<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Derivation;

use SqlFaker\Grammar\Symbol;

/**
 * A leftmost derivation frontier with capped occurrence counters and an output obligation.
 */
final class CompletionState
{
    /**
     * @param list<Symbol> $symbols
     * @param array<string, int> $occurrences
     */
    public function __construct(
        public readonly array $symbols,
        public readonly array $occurrences,
        public readonly bool $nonEmpty,
        public readonly int $spent,
        public readonly ?self $parent = null,
    ) {
    }

    /**
     * Omits spent cost so revisiting the same frontier cannot improve it by adding a recursive cycle.
     */
    public function key(): string
    {
        return serialize([array_map(static fn (Symbol $symbol): string => $symbol->value(), $this->symbols), $this->occurrences, $this->nonEmpty]);
    }
}
