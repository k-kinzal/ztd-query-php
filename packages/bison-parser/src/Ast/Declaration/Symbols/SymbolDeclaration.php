<?php

declare(strict_types=1);

namespace BisonParser\Ast\Declaration\Symbols;

use BisonParser\Ast\Declaration\Declaration;
use BisonParser\Ast\Location;

/**
 * A `%token`, `%nterm` or `%type` directive and the symbols it lists.
 *
 * Each entry carries the tag in force where it was written, so
 * `%token <a> X <b> Y` yields two entries with different tags.
 *
 * @visibility public
 *
 * @example Reading typed symbols
 *     $file = (new \BisonParser\Parser())->parse("%type <node> expr stmt\n%%\nexpr: 'a';\n");
 *     $file->declarations[0]->class->value // => 'type'
 *     array_map(static fn ($entry) => $entry->symbol->value, $file->declarations[0]->entries) // => ['expr', 'stmt']
 */
final class SymbolDeclaration implements Declaration
{
    /**
     * @param SymbolClass $class Which directive it is
     * @param list<SymbolEntry> $entries The symbols in order, at least one
     * @param Location $location Where the directive is written
     */
    public function __construct(
        public readonly SymbolClass $class,
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
