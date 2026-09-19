<?php

declare(strict_types=1);

namespace LemonParser\Ast\Declaration;

use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;

/**
 * `%token TOKEN TOKEN... .`: terminals named early to fix their numbering.
 *
 * @visibility public
 *
 * @example Reading a token declaration
 *     $file = (new \LemonParser\Parser())->parse("%token SEMI LP RP.\n");
 *     array_map(static fn ($symbol) => $symbol->name, $file->declarations()[0]->symbols) // => ['SEMI', 'LP', 'RP']
 */
final class TokenDeclaration implements Declaration
{
    /**
     * @param list<Symbol> $symbols The terminals, in order
     * @param Location $location Where the `%` sign is
     */
    public function __construct(
        public readonly array $symbols,
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
