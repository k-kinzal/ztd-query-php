<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Declaration;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A routine defined by one string that the routine's language interprets (AS 'definition').
 * @visibility public
 * @example Reading the definition text
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f() RETURNS integer LANGUAGE sql AS 'SELECT 1'");
 *     $statement->implementation->body->definition->text // => "'SELECT 1'"
 */
final class DefinitionBody implements RoutineBody
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $definition)
    {
        BodyInvariant::text($definition);
    }
}
