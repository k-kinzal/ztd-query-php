<?php

declare(strict_types=1);

namespace BisonParser\Ast\Rule;

use BisonParser\Ast\Location;

/**
 * One thing written on the right-hand side of a rule.
 *
 * @visibility public
 *
 * @example Every item knows where it was written
 *     $file = (new \BisonParser\Parser())->parse("%%\ns: 'a' %empty ;\n");
 *     (string) $file->rules()[0]->alternatives[0]->items[1]->location() // => '2:8'
 */
interface RhsItem
{
    /**
     * Answers where the item begins.
     *
     * @return Location Line and column of its first character
     */
    public function location(): Location;
}
