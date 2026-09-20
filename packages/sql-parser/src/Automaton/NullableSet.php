<?php

declare(strict_types=1);

namespace SqlParser\Automaton;

use SqlParser\Grammar\Grammar;

/**
 * The nonterminals that can derive the empty string.
 *
 * @visibility root
 */
final class NullableSet
{
    /**
     * @var array<int, true>
     */
    private readonly array $nullable;

    /**
     * Computes the set by iterating over the rules until nothing changes.
     *
     * @param Grammar $grammar Grammar to inspect
     */
    public function __construct(Grammar $grammar)
    {
        $nullable = [];
        do {
            $changed = false;
            foreach ($grammar->rules as $rule) {
                if (isset($nullable[$rule->lhs])) {
                    continue;
                }
                foreach ($rule->rhs as $symbol) {
                    if (!isset($nullable[$symbol])) {
                        continue 2;
                    }
                }
                $nullable[$rule->lhs] = true;
                $changed = true;
            }
        } while ($changed);
        $this->nullable = $nullable;
    }

    /**
     * Reports whether a symbol derives the empty string.
     *
     * @param int $symbol Symbol number
     *
     * @return bool True for a nullable nonterminal, false for terminals and other nonterminals
     */
    public function isNullable(int $symbol): bool
    {
        return isset($this->nullable[$symbol]);
    }

    /**
     * Reports whether every symbol from a position onward derives the empty string.
     *
     * @param list<int> $symbols Symbols to inspect
     * @param int $from Position to start at
     *
     * @return bool True when the tail is empty or all of it is nullable
     */
    public function tailNullable(array $symbols, int $from): bool
    {
        for ($index = $from, $count = count($symbols); $index < $count; $index++) {
            if (!isset($this->nullable[$symbols[$index]])) {
                return false;
            }
        }

        return true;
    }
}
