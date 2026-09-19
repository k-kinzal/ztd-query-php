<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

use BisonParser\Ast\Location;

/**
 * A `%param`, `%lex-param` or `%parse-param` directive and its braced arguments.
 *
 * @visibility public
 *
 * @example Reading parser parameters
 *     $file = (new \BisonParser\Parser())->parse("%parse-param {int *nastiness} {int *randomness}\n%%\ns: 'a';\n");
 *     $file->declarations[0]->kind->value // => 'parse-param'
 *     $file->declarations[0]->codes // => ['int *nastiness', 'int *randomness']
 */
final class Param implements Declaration
{
    /**
     * @param ParamKind $kind Which parser functions receive the arguments
     * @param list<string> $codes Each argument's code without its braces
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly ParamKind $kind,
        public readonly array $codes,
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
