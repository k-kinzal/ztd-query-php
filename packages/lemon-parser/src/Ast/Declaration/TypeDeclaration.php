<?php

declare(strict_types=1);

namespace LemonParser\Ast\Declaration;

use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;

/**
 * `%type symbol { type }`: the C type of a nonterminal's value.
 *
 * @visibility public
 *
 * @example Reading a type
 *     $file = (new \LemonParser\Parser())->parse("%type expr {Expr*}\n");
 *     $type = $file->declarations()[0];
 *     [$type->symbol->name, $type->value] // => ['expr', 'Expr*']
 */
final class TypeDeclaration implements Declaration
{
    /**
     * @param Symbol $symbol The symbol being typed
     * @param string $value The argument without its delimiters
     * @param ArgumentForm $form How the argument was written
     * @param Location $location Where the `%` sign is
     */
    public function __construct(
        public readonly Symbol $symbol,
        public readonly string $value,
        public readonly ArgumentForm $form,
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
