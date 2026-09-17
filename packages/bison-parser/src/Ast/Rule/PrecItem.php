<?php

declare(strict_types=1);

namespace BisonParser\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;

/**
 * A `%prec` modifier naming the token whose precedence the alternative takes.
 *
 * @visibility public
 *
 * @example Reading a precedence modifier
 *     $file = (new \BisonParser\Parser())->parse("%%\nexpr: '-' expr %prec NEG ;\n");
 *     $file->rules()[0]->alternatives[0]->items[2]->symbol->value // => 'NEG'
 */
final class PrecItem implements RhsItem
{
    /**
     * @param Symbol $symbol The token named
     * @param Location $location Where the modifier is written
     */
    public function __construct(
        public readonly Symbol $symbol,
        public readonly Location $location,
    ) {
    }

    /**
     * Answers where the item begins.
     *
     * @return Location Line and column of the modifier
     */
    public function location(): Location
    {
        return $this->location;
    }
}
