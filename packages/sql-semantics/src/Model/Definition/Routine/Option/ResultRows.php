<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Option;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Estimates the number of rows a set-returning function returns.
 * @visibility public
 * @example Reading the estimate
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER FUNCTION f() ROWS 20');
 *     $statement->changes[0]->rows->text // => '20'
 */
final class ResultRows implements RoutineOption
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Literal $rows)
    {
        OptionInvariant::estimate($rows);
    }
}
