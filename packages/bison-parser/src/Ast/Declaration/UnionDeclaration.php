<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

use BisonParser\Ast\Location;

/**
 * A `%union` directive, its optional name, and its members.
 *
 * @visibility public
 *
 * @example Reading a union
 *     $file = (new \BisonParser\Parser())->parse("%union YYSTYPE { int i; char *s; }\n%%\ns: 'a';\n");
 *     $file->declarations[0]->name // => 'YYSTYPE'
 *     $file->declarations[0]->code // => ' int i; char *s; '
 */
final class UnionDeclaration implements Declaration
{
    /**
     * @param string|null $name The union's name, or null when none is given
     * @param string $code The members without the braces
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly ?string $name,
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
