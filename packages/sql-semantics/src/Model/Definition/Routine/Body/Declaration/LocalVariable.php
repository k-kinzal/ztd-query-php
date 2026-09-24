<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Declaration;

use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A routine parameter or DECLAREd local variable, the storage a local-variable reference names.
 * @visibility public
 * @example Reading the variable a reference names
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE FUNCTION f(a INT) RETURNS INT RETURN a');
 *     $statement->body->value->variable->name // => 'a'
 */
final class LocalVariable
{
    /**
     * Requires a nonempty name.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly DeclaredDomain $domain)
    {
        if ($name === '') {
            throw new InvalidStructure('A local variable requires a name.');
        }
    }
}
