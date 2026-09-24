<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Declaration;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * An inline SQL body that returns one expression, bound with the routine parameters in scope (RETURN expression).
 * @visibility public
 * @example Reading the returned expression
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE FUNCTION f(n integer) RETURNS integer RETURN n + 1');
 *     $statement->implementation->body->value->kind->value // => 'operator'
 */
final class ReturnBody implements RoutineBody
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Expression $value)
    {
        if ($value->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A routine body returns a PostgreSQL expression.');
        }
    }
}
