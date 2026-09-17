<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

use BisonParser\Ast\Location;

/**
 * A directive that takes no argument, such as `%debug` or `%glr-parser`.
 *
 * The name is the canonical spelling with hyphens, `%pure-parser` for both
 * `%pure-parser` and `%pure_parser`; the raw spelling is kept as written.
 *
 * @visibility public
 *
 * @example Reading a flag
 *     $file = (new \BisonParser\Parser())->parse("%pure_parser\n%%\ns: 'a';\n");
 *     $file->declarations[0]->name // => 'pure-parser'
 *     $file->declarations[0]->raw // => '%pure_parser'
 */
final class Flag implements Declaration
{
    /**
     * @param string $name Canonical directive name without the percent sign
     * @param string $raw The directive as written, percent sign included
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly string $name,
        public readonly string $raw,
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
