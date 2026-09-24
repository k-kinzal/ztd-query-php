<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A named input parameter of a stored function.
 * @visibility public
 * @example Reading a function parameter
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE FUNCTION f(a INT) RETURNS INT RETURN a');
 *     $statement->parameters[0]->name // => 'a'
 */
final class FunctionParameter
{
    /**
     * Requires a nonempty parameter name.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly DeclaredDomain $domain)
    {
        if ($name === '') {
            throw new InvalidStructure('A stored function parameter requires a name.');
        }
    }
}
