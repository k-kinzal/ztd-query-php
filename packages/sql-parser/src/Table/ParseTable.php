<?php

declare(strict_types=1);

namespace SqlParser\Table;

use SqlParser\Grammar\SymbolTable;

/**
 * The LALR(1) parse table of one grammar.
 *
 * Each state has explicit actions for some symbols and a default for the
 * rest. Looking an action up follows Lemon's order so that both generators'
 * tables read the same way: the token itself, then the token it falls back
 * to, then the wildcard, then the state's default.
 *
 * @visibility root
 */
final class ParseTable
{
    /**
     * @param SymbolTable $symbols Every symbol, numbered as the rows refer to them
     * @param list<TableRule> $rules Every rule, the augmented start rule first
     * @param list<int> $defaults Default action code of each state, or the error code
     * @param ActionRows $rows Explicit actions of each state
     * @param array<int, int> $fallbacks Terminal to retry with by the terminal that failed
     * @param int|null $wildcard Terminal whose action applies to any other terminal without one
     * @param array<int, array<int, list<int>>> $alternatives Unranked alternatives by state and terminal
     */
    public function __construct(
        public readonly SymbolTable $symbols,
        public readonly array $rules,
        public readonly array $defaults,
        public readonly ActionRows $rows,
        public readonly array $fallbacks = [],
        public readonly ?int $wildcard = null,
        public readonly array $alternatives = [],
    ) {
    }

    /**
     * Answers the action a state takes on a symbol.
     *
     * @param int $state State number
     * @param int $symbol Terminal or nonterminal number
     *
     * @return int The action code, the error code when the state rejects the symbol
     */
    public function action(int $state, int $symbol): int
    {
        $row = $this->rows->row($state);
        if (isset($row[$symbol])) {
            return $row[$symbol];
        }
        $fallback = $this->fallbacks[$symbol] ?? null;
        if ($fallback !== null && isset($row[$fallback])) {
            return $row[$fallback];
        }
        if ($this->wildcard !== null && $symbol > 0 && $symbol < $this->symbols->terminalCount() && isset($row[$this->wildcard])) {
            return $row[$this->wildcard];
        }

        return $this->defaults[$state] ?? ActionCode::ERROR;
    }

    /**
     * Answers the terminals a state has an action for, so an error can say what it expected.
     *
     * @param int $state State number
     *
     * @return list<int> Terminal numbers with a shift or reduce, in number order
     */
    public function expectedTerminals(int $state): array
    {
        $terminals = [];
        foreach ($this->rows->row($state) as $symbol => $action) {
            if ($action !== ActionCode::ERROR && $this->symbols->isTerminal($symbol)) {
                $terminals[] = $symbol;
            }
        }
        sort($terminals);

        return $terminals;
    }

    /**
     * Answers how many states the table has.
     *
     * @return int State count
     */
    public function stateCount(): int
    {
        return count($this->defaults);
    }
}
