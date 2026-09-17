<?php

declare(strict_types=1);

namespace LemonParser\Ast\Declaration;

use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;

/**
 * `%wildcard TOKEN.`: the terminal that matches any token the parser cannot otherwise use.
 *
 * @visibility public
 *
 * @example Reading the wildcard
 *     $file = (new \LemonParser\Parser())->parse("%wildcard ANY.\n");
 *     $file->declarations()[0]->symbol?->name // => "ANY"
 */
final class Wildcard implements Declaration
{
    /**
     * @param Symbol|null $symbol The terminal, or null when the declaration names none
     * @param Location $location Where the `%` sign is
     */
    public function __construct(
        public readonly ?Symbol $symbol,
        public readonly Location $location,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function location(): Location
    {
        return $this->location;
    }
}
