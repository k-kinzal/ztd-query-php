<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

use BisonParser\Ast\Location;

/**
 * A directive that takes a string, such as `%name-prefix "yy"` or `%require "3.8"`.
 *
 * `%header` and its deprecated spelling `%defines` may omit the string.
 *
 * @visibility public
 *
 * @example Reading an option
 *     $file = (new \BisonParser\Parser())->parse("%name-prefix=\"base_yy\"\n%%\ns: 'a';\n");
 *     $file->declarations[0]->name // => 'name-prefix'
 *     $file->declarations[0]->value // => 'base_yy'
 */
final class Option implements Declaration
{
    /**
     * @param string $name Canonical directive name without the percent sign
     * @param string $raw The directive as written, percent sign included
     * @param string|null $value The decoded string, or null when it was omitted
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly string $name,
        public readonly string $raw,
        public readonly ?string $value,
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
