<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

use BisonParser\Ast\Location;

/**
 * An `%initial-action` directive and its code.
 *
 * @visibility public
 *
 * @example Reading the initial action
 *     $file = (new \BisonParser\Parser())->parse("%initial-action { @\$.begin.filename = 0; }\n%%\ns: 'a';\n");
 *     $file->declarations[0]->code // => ' @$.begin.filename = 0; '
 */
final class InitialAction implements Declaration
{
    /**
     * @param string $code The code without its braces
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly string $code,
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
