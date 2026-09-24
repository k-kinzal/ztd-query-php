<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Stored;

/**
 * The kind of stored program that owns a body, which decides the statements the body may contain.
 * @visibility public
 * @example Reading the program kind
 *     \SqlSemantics\Model\Definition\Routine\Stored\ProgramKind::Function->value // => 'FUNCTION'
 */
enum ProgramKind: string
{
    case Procedure = 'PROCEDURE';
    case Function = 'FUNCTION';
    case Trigger = 'TRIGGER';
    case Event = 'EVENT';
}
