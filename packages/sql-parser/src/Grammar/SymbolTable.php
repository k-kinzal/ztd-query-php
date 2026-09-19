<?php

declare(strict_types=1);

namespace SqlParser\Grammar;

/**
 * Numbers every symbol of a grammar, terminals first.
 *
 * Terminal identifiers run from zero, with the end marker at zero, and the
 * nonterminals continue after the last terminal. The automaton and the parse
 * table refer to symbols by these numbers only.
 *
 * @visibility root
 */
final class SymbolTable
{
    /**
     * The name of the terminal that marks the end of the input.
     */
    public const END = '$end';

    /**
     * @var array<string, int>
     */
    private readonly array $ids;

    /**
     * @param list<string> $terminals Terminal names, the end marker first
     * @param list<string> $nonterminals Nonterminal names in declaration order
     *
     * @throws GrammarException When the end marker is missing or a name repeats
     */
    public function __construct(
        private readonly array $terminals,
        private readonly array $nonterminals,
    ) {
        if (($terminals[0] ?? null) !== self::END) {
            throw new GrammarException('The first terminal must be the end marker ' . self::END);
        }
        $ids = [];
        foreach ([...$terminals, ...$nonterminals] as $id => $name) {
            if (isset($ids[$name])) {
                throw new GrammarException("Symbol '{$name}' is declared twice");
            }
            $ids[$name] = $id;
        }
        $this->ids = $ids;
    }

    /**
     * Answers the number of a symbol, or null when the name is unknown.
     *
     * @param string $name Symbol name as the grammar spells it
     *
     * @return int|null The symbol number
     */
    public function id(string $name): ?int
    {
        return $this->ids[$name] ?? null;
    }

    /**
     * Answers the name of a symbol.
     *
     * @param int $id Symbol number
     *
     * @return string The name as the grammar spells it
     *
     * @throws GrammarException When no symbol has that number
     */
    public function name(int $id): string
    {
        $terminalCount = count($this->terminals);
        if ($id >= 0 && $id < $terminalCount) {
            return $this->terminals[$id];
        }
        $name = $this->nonterminals[$id - $terminalCount] ?? null;
        if ($id < 0 || $name === null) {
            throw new GrammarException("No symbol is numbered {$id}");
        }

        return $name;
    }

    /**
     * Reports whether a number denotes a terminal.
     *
     * @param int $id Symbol number
     *
     * @return bool True for terminals, false for nonterminals
     */
    public function isTerminal(int $id): bool
    {
        return $id < count($this->terminals);
    }

    /**
     * Answers how many terminals there are, the end marker included.
     *
     * @return int Terminal count
     */
    public function terminalCount(): int
    {
        return count($this->terminals);
    }

    /**
     * Answers how many symbols there are in total.
     *
     * @return int Terminal count plus nonterminal count
     */
    public function count(): int
    {
        return count($this->terminals) + count($this->nonterminals);
    }

    /**
     * Answers every terminal name in number order.
     *
     * @return list<string> Terminal names
     */
    public function terminals(): array
    {
        return $this->terminals;
    }

    /**
     * Answers every nonterminal name in number order.
     *
     * @return list<string> Nonterminal names
     */
    public function nonterminals(): array
    {
        return $this->nonterminals;
    }
}
