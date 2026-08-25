<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

/**
 * Represents a single production (right-hand side) in a grammar rule.
 *
 * A production is a sequence of symbols that a non-terminal can expand to.
 */
final class Production
{
    /**
     * @param list<Symbol> $symbols Sequence of terminal and non-terminal symbols
     */
    public function __construct(
        public readonly array $symbols,
    ) {
    }

    /**
     * Reports whether one of the symbols is a given terminal.
     *
     * @param string $value Terminal to look for
     *
     * @return bool True when the production writes that terminal
     */
    public function hasTerminal(string $value): bool
    {
        foreach ($this->symbols as $symbol) {
            if ($symbol instanceof Terminal && $symbol->value === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether one of the symbols expands to a given rule.
     *
     * @param string $value Non-terminal to look for
     *
     * @return bool True when the production expands to that rule
     */
    public function hasNonTerminal(string $value): bool
    {
        foreach ($this->symbols as $symbol) {
            if ($symbol instanceof NonTerminal && $symbol->value === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether the production writes anything at all by itself.
     *
     * A production made only of non-terminals writes nothing until those are
     * expanded, which is what tells a rule apart from one that anchors on a
     * keyword.
     *
     * @return bool True when at least one symbol is a terminal
     */
    public function hasAnyTerminal(): bool
    {
        foreach ($this->symbols as $symbol) {
            if ($symbol instanceof Terminal) {
                return true;
            }
        }

        return false;
    }

    /**
     * Answers the symbol at one position when it is a terminal.
     *
     * @param int $index Position in the production
     *
     * @return Terminal|null Terminal at that position, or null when it is absent or a non-terminal
     */
    public function terminalAt(int $index): ?Terminal
    {
        $symbol = $this->symbols[$index] ?? null;

        return $symbol instanceof Terminal ? $symbol : null;
    }

    /**
     * Answers the symbol at one position when it is a non-terminal.
     *
     * @param int $index Position in the production
     *
     * @return NonTerminal|null Non-terminal at that position, or null when it is absent or a terminal
     */
    public function nonTerminalAt(int $index): ?NonTerminal
    {
        $symbol = $this->symbols[$index] ?? null;

        return $symbol instanceof NonTerminal ? $symbol : null;
    }

    /**
     * Answers the rules the production expands to, in order.
     *
     * @return list<string> Non-terminal names in the order they are written
     */
    public function nonTerminalNames(): array
    {
        $names = [];
        foreach ($this->symbols as $symbol) {
            if ($symbol instanceof NonTerminal) {
                $names[] = $symbol->value;
            }
        }

        return $names;
    }
}
