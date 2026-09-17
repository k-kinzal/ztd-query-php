<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

use BisonParser\Ast\Location;

/**
 * A `%code` directive, with the qualifier that says where the code goes.
 *
 * @visibility public
 *
 * @example Reading a code block
 *     $file = (new \BisonParser\Parser())->parse("%code requires { #include <stdint.h> }\n%%\ns: 'a';\n");
 *     $file->declarations[0]->qualifier // => 'requires'
 *     $file->declarations[0]->code // => ' #include <stdint.h> '
 */
final class Code implements Declaration
{
    /**
     * @param string|null $qualifier The qualifier such as `requires`, or null for unqualified code
     * @param string $code The code without its braces
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly ?string $qualifier,
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
