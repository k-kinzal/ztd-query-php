<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Declaration;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * SET variable = expression for a parameter or DECLAREd local variable.
 * @visibility public
 * @example Reading an assigned local variable
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(OUT a INT) SET a = 1');
 *     $statement->body->assignments[0]->target->variable->name // => 'a'
 */
final class LocalAssignment
{
    /**
     * Requires a MySQL value expression.
     * @throws InvalidStructure
     */
    public function __construct(public readonly LocalVariableReference $target, public readonly Expression $value)
    {
        if ($value->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A local variable assignment requires a MySQL expression.');
        }
    }
}
