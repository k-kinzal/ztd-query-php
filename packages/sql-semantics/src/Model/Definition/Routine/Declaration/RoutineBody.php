<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Declaration;

/**
 * The implementation of a routine: a definition string, an object file symbol, or an inline SQL body.
 * @visibility public
 * @example Classifying an inline body
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE FUNCTION f() RETURNS integer RETURN 1');
 *     $statement->implementation->body instanceof \SqlSemantics\Model\Definition\Routine\Declaration\RoutineBody // => true
 */
interface RoutineBody
{
}
