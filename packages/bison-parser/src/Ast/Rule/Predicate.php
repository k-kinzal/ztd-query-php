<?php

declare(strict_types=1);

namespace BisonParser\Ast\Rule;

use BisonParser\Ast\Location;

/**
 * A semantic predicate `%?{ ... }` on a right-hand side.
 *
 * @visibility public
 *
 * @example Reading a predicate
 *     $file = (new \BisonParser\Parser())->parse("%%\ns: %?{ new_syntax } 'a' ;\n");
 *     $file->rules()[0]->alternatives[0]->items[0]->code // => ' new_syntax '
 */
final class Predicate implements RhsItem
{
    /**
     * @param string $code The code without its braces
     * @param Location $location Where the `%?` is written
     */
    public function __construct(
        public readonly string $code,
        public readonly Location $location,
    ) {
    }

    /**
     * Answers where the item begins.
     *
     * @return Location Line and column of the `%?`
     */
    public function location(): Location
    {
        return $this->location;
    }
}
