<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;

/**
 * An instruction shape that can provide a stored function's RETURN instruction.
 *
 * This includes RETURN itself and compound control structures containing
 * instructions or handlers. A function declaration additionally requires an
 * actual RETURN in that structure and reports its absence as a definition
 * condition; the instruction need not be reachable at execution time.
 * Cursor instructions and ordinary SQL statements cannot supply that return.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/return.html.
 *
 * @visibility public
 * @example Reading a function's instruction body
 *     $function = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE FUNCTION f() RETURNS INT RETURN 1');
 *     $function->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\FunctionBody // => true
 */
interface FunctionBody extends ProgramStatement
{
}
