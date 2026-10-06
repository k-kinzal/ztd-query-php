<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

/**
 * The kinds of stored program: procedure, function, trigger and event.
 *
 * Each case holds the keyword that names the kind in CREATE, ALTER and DROP.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-objects.html.
 *
 * @visibility public
 * @example Reading the keyword of a kind
 *     \SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Trigger->value // => 'TRIGGER'
 */
enum ProgramKind: string
{
    case Procedure = 'PROCEDURE';
    case Function = 'FUNCTION';
    case Trigger = 'TRIGGER';
    case Event = 'EVENT';
}
