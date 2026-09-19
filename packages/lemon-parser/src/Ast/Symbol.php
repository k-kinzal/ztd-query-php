<?php

declare(strict_types=1);

namespace LemonParser\Ast;

/**
 * A grammar symbol named where it is written.
 *
 * Lemon tells terminals from nonterminals by the first letter of the name:
 * an upper-case letter starts a terminal, a lower-case letter a nonterminal.
 *
 * @visibility public
 *
 * @example Telling a terminal from a nonterminal
 *     $file = (new \LemonParser\Parser())->parse("expr ::= expr PLUS expr.\n");
 *     $rule = $file->rules()[0];
 *     [$rule->lhs->isTerminal(), $rule->items[1]->symbols[0]->isTerminal()] // => [false, true]
 */
final class Symbol
{
    /**
     * @param string $name The name as written
     * @param Location $location Where it is written
     */
    public function __construct(
        public readonly string $name,
        public readonly Location $location,
    ) {
    }

    /**
     * Reports whether the name starts with an upper-case letter, which makes it a terminal.
     *
     * @return bool True for a terminal
     */
    public function isTerminal(): bool
    {
        return ctype_upper($this->name[0] ?? '');
    }

    /**
     * Reports whether the name starts with a lower-case letter, which makes it a nonterminal.
     *
     * @return bool True for a nonterminal
     */
    public function isNonterminal(): bool
    {
        return ctype_lower($this->name[0] ?? '');
    }
}
