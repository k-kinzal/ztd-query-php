<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

use BisonParser\Ast\Location;

/**
 * Host code between `%{` and `%}`, kept exactly as written.
 *
 * @visibility public
 *
 * @example Reading a prologue
 *     $file = (new \BisonParser\Parser())->parse("%{\n#include <stdio.h>\n%}\n%%\ns: 'a';\n");
 *     $file->declarations[0]->code // => "\n#include <stdio.h>\n"
 */
final class Prologue implements Declaration
{
    /**
     * @param string $code Text between the markers
     * @param Location $location Where the opening marker is written
     */
    public function __construct(
        public readonly string $code,
        public readonly Location $location,
    ) {
    }

    /**
     * Answers where the declaration begins.
     *
     * @return Location Line and column of the opening marker
     */
    public function location(): Location
    {
        return $this->location;
    }
}
