<?php

declare(strict_types=1);

namespace LemonParser\Ast\Declaration;

use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;

/**
 * `%token_class name TOKEN|TOKEN... .`: a nonterminal name standing for any of several terminals.
 *
 * @visibility public
 *
 * @example Reading a token class
 *     $file = (new \LemonParser\Parser())->parse("%token_class id ID|INDEXED.\n");
 *     $class = $file->declarations()[0];
 *     [$class->name->name, array_map(static fn ($symbol) => $symbol->name, $class->tokens)] // => ['id', ['ID', 'INDEXED']]
 */
final class TokenClass implements Declaration
{
    /**
     * @param Symbol $name The name of the class
     * @param list<Symbol> $tokens The terminals it stands for, in order
     * @param Location $location Where the `%` sign is
     */
    public function __construct(
        public readonly Symbol $name,
        public readonly array $tokens,
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
