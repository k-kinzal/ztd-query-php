<?php

declare(strict_types=1);

namespace LemonParser\Ast;

/**
 * One position on the right-hand side of a rule.
 *
 * Usually one symbol, but Lemon lets several terminals share a position,
 * written `A|B|C`; the position then matches any of them. An alias in
 * parentheses names the value of the position in the rule's code.
 *
 * @visibility public
 *
 * @example Reading a multi-terminal position
 *     $file = (new \LemonParser\Parser())->parse("cmd ::= BEGIN|START trans_opt(X).\n");
 *     $items = $file->rules()[0]->items;
 *     [$items[0]->isMultiTerminal(), array_map(static fn ($symbol) => $symbol->name, $items[0]->symbols), $items[1]->alias] // => [true, ['BEGIN', 'START'], 'X']
 */
final class RhsItem
{
    /**
     * @param non-empty-list<Symbol> $symbols The symbol, or the terminals that share the position
     * @param string|null $alias The name given to the position's value, or null
     */
    public function __construct(
        public readonly array $symbols,
        public readonly ?string $alias,
    ) {
    }

    /**
     * Reports whether several terminals share the position.
     *
     * @return bool True for `A|B` style positions
     */
    public function isMultiTerminal(): bool
    {
        return count($this->symbols) > 1;
    }

    /**
     * Answers where the position begins.
     *
     * @return Location Where the first symbol is written
     */
    public function location(): Location
    {
        return $this->symbols[0]->location;
    }
}
