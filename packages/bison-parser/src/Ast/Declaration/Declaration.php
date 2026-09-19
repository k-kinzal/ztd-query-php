<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration;

use BisonParser\Ast\Location;

/**
 * One `%`-directive or prologue block of a grammar file.
 *
 * @visibility public
 *
 * @example Every declaration knows where it was written
 *     $file = (new \BisonParser\Parser())->parse("%token A\n%%\ns: A;\n");
 *     (string) $file->declarations[0]->location() // => '1:1'
 */
interface Declaration
{
    /**
     * Answers where the declaration begins.
     *
     * @return Location Line and column of its first character
     */
    public function location(): Location;
}
