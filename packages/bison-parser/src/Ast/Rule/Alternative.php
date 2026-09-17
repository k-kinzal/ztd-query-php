<?php

declare(strict_types=1);

namespace BisonParser\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;

/**
 * One right-hand side of a rule, as the sequence of items written between `|` separators.
 *
 * Symbols, actions, predicates and modifiers are kept in the order they
 * appear, so an action followed by more symbols is where Bison would put
 * a mid-rule action.
 *
 * @visibility public
 *
 * @example Reading the symbols of an alternative
 *     $file = (new \BisonParser\Parser())->parse("%%\nexpr: expr '+' { \$\$ = 1; } expr %prec PLUS ;\n");
 *     $alternative = $file->rules()[0]->alternatives[0];
 *     count($alternative->items) // => 5
 *     array_map(static fn ($symbol) => $symbol->value, $alternative->symbols()) // => ['expr', '+', 'expr']
 */
final class Alternative
{
    /**
     * @param list<RhsItem> $items Everything written on the right-hand side, in order
     * @param Location $location Where the alternative begins, or where the `|` is for an empty one
     */
    public function __construct(
        public readonly array $items,
        public readonly Location $location,
    ) {
    }

    /**
     * Answers the symbols of the right-hand side, leaving actions and modifiers out.
     *
     * @return list<Symbol> The symbols in order
     */
    public function symbols(): array
    {
        $symbols = [];
        foreach ($this->items as $item) {
            if ($item instanceof SymbolItem) {
                $symbols[] = $item->symbol;
            }
        }

        return $symbols;
    }
}
