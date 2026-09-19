<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

use BisonParser\Ast\Location;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\Tag;

/**
 * A `%destructor` or `%printer` directive: code attached to symbols or type tags.
 *
 * @visibility public
 *
 * @example Reading a destructor
 *     $file = (new \BisonParser\Parser())->parse("%destructor { free (\$\$); } <str> ID\n%%\ns: 'a';\n");
 *     $file->declarations[0]->printer // => false
 *     $file->declarations[0]->targets[0]->name // => 'str'
 *     $file->declarations[0]->targets[1]->value // => 'ID'
 */
final class CodeProps implements Declaration
{
    /**
     * @param bool $printer Whether the directive is `%printer` rather than `%destructor`
     * @param string $code The code without its braces
     * @param list<Symbol|Tag> $targets The symbols and tags the code applies to, at least one
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly bool $printer,
        public readonly string $code,
        public readonly array $targets,
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
