<?php

declare(strict_types=1);

namespace BisonParser\Ast\Rule;

use BisonParser\Ast\Location;

/**
 * An `%expect` or `%expect-rr` modifier declaring the conflicts of one alternative.
 *
 * @visibility public
 *
 * @example Reading a per-rule expectation
 *     $file = (new \BisonParser\Parser())->parse("%%\ns: 'a' %expect 1 ;\n");
 *     $file->rules()[0]->alternatives[0]->items[1]->count // => 1
 */
final class ExpectItem implements RhsItem
{
    /**
     * @param int $count The declared number of conflicts
     * @param bool $reduceReduce Whether the modifier is `%expect-rr` rather than `%expect`
     * @param Location $location Where the modifier is written
     */
    public function __construct(
        public readonly int $count,
        public readonly bool $reduceReduce,
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
