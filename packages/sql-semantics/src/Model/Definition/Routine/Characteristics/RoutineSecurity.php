<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Characteristics;

/**
 * Selects whose privileges apply when executing a stored routine.
 * @visibility public
 * @example Inspecting a declared routine property
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('ALTER PROCEDURE p SQL SECURITY INVOKER');
 *     $statement->changes->security === \SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity::Invoker // => true
 */
enum RoutineSecurity: string
{
    case Definer = 'DEFINER';
    case Invoker = 'INVOKER';
}
