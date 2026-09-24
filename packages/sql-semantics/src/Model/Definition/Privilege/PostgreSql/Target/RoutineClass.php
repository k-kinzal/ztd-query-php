<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql\Target;

/**
 * The routine lookup class; ROUTINE matches both functions and procedures.
 * @visibility public
 * @example Inspecting the class
 *     \SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineClass::Procedure->value // => 'PROCEDURE'
 */
enum RoutineClass: string
{
    case Function = 'FUNCTION';
    case Procedure = 'PROCEDURE';
    case Routine = 'ROUTINE';
}
