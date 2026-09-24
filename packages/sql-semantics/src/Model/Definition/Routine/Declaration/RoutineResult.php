<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Declaration;

use SqlSemantics\Model\Definition\Routine\ArgumentTypeInvariant;
use SqlSemantics\Model\Definition\Routine\ColumnTypeReference;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * The declared result type of a function, a single value or a set of values (RETURNS [SETOF] type).
 * @visibility public
 * @example Reading a set-returning result
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE FUNCTION f() RETURNS SETOF text LANGUAGE sql AS 'SELECT 1'");
 *     $statement->result->setOf // => true
 *     $statement->result->type->name // => 'text'
 */
final class RoutineResult
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly TypeDescriptor|ColumnTypeReference $type, public readonly bool $setOf = false)
    {
        ArgumentTypeInvariant::validate($type);
    }
}
