<?php

declare(strict_types=1);

namespace BisonParser\Ast\Rule;

use BisonParser\Ast\Location;

/**
 * A `%dprec` modifier ranking the alternative among GLR ambiguities.
 *
 * @visibility public
 *
 * @example Reading a dynamic precedence
 *     $file = (new \BisonParser\Parser())->parse("%glr-parser\n%%\ns: 'a' %dprec 2 ;\n");
 *     $file->rules()[0]->alternatives[0]->items[1]->value // => 2
 */
final class DprecItem implements RhsItem
{
    /**
     * @param int $value The rank
     * @param Location $location Where the modifier is written
     */
    public function __construct(
        public readonly int $value,
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
