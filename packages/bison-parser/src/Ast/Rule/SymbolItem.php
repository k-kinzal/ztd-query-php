<?php

declare(strict_types=1);

namespace BisonParser\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;

/**
 * A symbol on a right-hand side, with the `[name]` reference that may follow it.
 *
 * @visibility public
 *
 * @example Reading a named symbol
 *     $file = (new \BisonParser\Parser())->parse("%%\ns: expr[left] '+' expr[right] ;\n");
 *     $item = $file->rules()[0]->alternatives[0]->items[0];
 *     $item->symbol->value // => 'expr'
 *     $item->namedReference // => 'left'
 */
final class SymbolItem implements RhsItem
{
    /**
     * @param Symbol $symbol The symbol
     * @param string|null $namedReference The `[name]` after it, or null when none is given
     */
    public function __construct(
        public readonly Symbol $symbol,
        public readonly ?string $namedReference,
    ) {
    }

    /**
     * Answers where the item begins.
     *
     * @return Location Line and column of the symbol
     */
    public function location(): Location
    {
        return $this->symbol->location;
    }
}
