<?php

declare(strict_types=1);

namespace BisonParser\Ast\Rule;

use BisonParser\Ast\Location;

/**
 * A `%merge` modifier naming the function that merges GLR ambiguities.
 *
 * @visibility public
 *
 * @example Reading a merge function
 *     $file = (new \BisonParser\Parser())->parse("%glr-parser\n%%\ns: 'a' %merge <stmtMerge> ;\n");
 *     $file->rules()[0]->alternatives[0]->items[1]->tag // => 'stmtMerge'
 */
final class MergeItem implements RhsItem
{
    /**
     * @param string $tag The function name written between angle brackets
     * @param Location $location Where the modifier is written
     */
    public function __construct(
        public readonly string $tag,
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
