<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;

/**
 * A `%start` directive naming the start symbol, or several of them.
 *
 * @visibility public
 *
 * @example Reading the start symbol
 *     $file = (new \BisonParser\Parser())->parse("%start program\n%%\nprogram: 'a';\n");
 *     $file->declarations[0]->symbols[0]->value // => 'program'
 */
final class Start implements Declaration
{
    /**
     * @param list<Symbol> $symbols The named start symbols, at least one
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly array $symbols,
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
