<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Routine;

/**
 * The stored routine kind an inspection reports on.
 * @visibility public
 * @example Inspecting the routine kind of a status listing
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW PROCEDURE STATUS');
 *     $statement->routine === \SqlSemantics\Model\Query\Inspection\Routine\RoutineKind::Procedure // => true
 */
enum RoutineKind: string
{
    case Function = 'FUNCTION';
    case Procedure = 'PROCEDURE';
}
