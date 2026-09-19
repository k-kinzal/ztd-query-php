<?php

declare(strict_types=1);

namespace LemonParser\Ast\Declaration;

use LemonParser\Ast\Location;

/**
 * Something written after a `%` sign.
 *
 * @visibility public
 *
 * @example Walking the declarations of a file
 *     $file = (new \LemonParser\Parser())->parse("%token_prefix TK_\n%left PLUS.\n");
 *     array_map(static fn (\LemonParser\Ast\Declaration\Declaration $d) => (string) $d->location(), $file->declarations()) // => ['1:1', '2:1']
 */
interface Declaration
{
    /**
     * Answers where the `%` sign is written.
     *
     * @return Location The position
     */
    public function location(): Location;
}
