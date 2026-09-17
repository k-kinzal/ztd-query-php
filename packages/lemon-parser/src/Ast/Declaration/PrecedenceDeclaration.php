<?php

declare(strict_types=1);

namespace LemonParser\Ast\Declaration;

use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;

/**
 * `%left`, `%right` or `%nonassoc` followed by terminals and a period.
 *
 * Each declaration ranks its terminals one level above those of the
 * declarations before it, which is why the order in the file matters.
 *
 * @visibility public
 *
 * @example Reading a precedence declaration
 *     $file = (new \LemonParser\Parser())->parse("%left PLUS MINUS.\n%left STAR SLASH.\n");
 *     $second = $file->declarations()[1];
 *     [$second->associativity->value, array_map(static fn ($symbol) => $symbol->name, $second->symbols)] // => ['left', ['STAR', 'SLASH']]
 */
final class PrecedenceDeclaration implements Declaration
{
    /**
     * @param Associativity $associativity Which keyword it is
     * @param list<Symbol> $symbols The terminals, in order
     * @param Location $location Where the `%` sign is
     */
    public function __construct(
        public readonly Associativity $associativity,
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
