<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

use BisonParser\Ast\Location;

/**
 * An `%expect` or `%expect-rr` directive declaring how many conflicts the grammar has.
 *
 * @visibility public
 *
 * @example Reading the expected conflicts
 *     $file = (new \BisonParser\Parser())->parse("%expect 59\n%%\ns: 'a';\n");
 *     $file->declarations[0]->count // => 59
 *     $file->declarations[0]->reduceReduce // => false
 */
final class Expect implements Declaration
{
    /**
     * @param int $count The declared number of conflicts
     * @param bool $reduceReduce Whether the directive is `%expect-rr` rather than `%expect`
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly int $count,
        public readonly bool $reduceReduce,
        public readonly Location $location,
    ) {
    }

    /**
     * Answers where the declaration begins.
     *
     * @return Location Line and column of the percent sign
     */
    public function location(): Location
    {
        return $this->location;
    }
}
