<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration\Symbols;

use BisonParser\Ast\Declaration\Declaration;
use BisonParser\Ast\Location;

/**
 * A `%left`, `%right`, `%nonassoc` or `%precedence` directive and the tokens it ranks.
 *
 * Every directive ranks its tokens above those of the directives before it.
 * A string here is a token of its own, not an alias of the identifier
 * before it, which is how Bison reads `%left FOO "foo"`.
 *
 * @visibility public
 *
 * @example Reading operator precedence
 *     $file = (new \BisonParser\Parser())->parse("%left '+' '-'\n%left '*'\n%%\ns: 'a';\n");
 *     $file->declarations[1]->associativity->value // => 'left'
 *     $file->declarations[1]->entries[0]->symbol->value // => '*'
 */
final class PrecedenceDeclaration implements Declaration
{
    /**
     * @param Associativity $associativity Which directive it is
     * @param list<SymbolEntry> $entries The tokens in order, at least one
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly Associativity $associativity,
        public readonly array $entries,
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
