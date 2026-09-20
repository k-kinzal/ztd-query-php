<?php

declare(strict_types=1);

namespace LemonParser\Ast\Declaration;

use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;

/**
 * `%destructor symbol { code }`: code run when a value of the symbol is discarded.
 *
 * @visibility public
 *
 * @example Reading a destructor
 *     $file = (new \LemonParser\Parser())->parse("%destructor expr { freeExpr($$); }\n");
 *     $destructor = $file->declarations()[0];
 *     [$destructor->symbol->name, $destructor->value] // => ['expr', ' freeExpr($$); ']
 */
final class Destructor implements Declaration
{
    /**
     * @param Symbol $symbol The symbol whose values it destroys
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
