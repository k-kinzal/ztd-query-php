<?php

declare(strict_types=1);

namespace BisonParser\Ast\Rule;

use BisonParser\Ast\Location;

/**
 * The `%empty` marker that documents an empty right-hand side.
 *
 * @visibility public
 *
 * @example Reading an empty alternative
 *     $file = (new \BisonParser\Parser())->parse("%%\nopt: %empty | 'a' ;\n");
 *     $file->rules()[0]->alternatives[0]->items[0] instanceof \BisonParser\Ast\Rule\EmptyItem // => true
 */
final class EmptyItem implements RhsItem
{
    /**
     * @param Location $location Where the marker is written
     */
    public function __construct(public readonly Location $location)
    {
    }

    /**
     * Answers where the item begins.
     *
     * @return Location Line and column of the marker
     */
    public function location(): Location
    {
        return $this->location;
    }
}
