<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Comment;

/**
 * Why the server stops reading a hint comment, with the text of the warning it raises.
 *
 * The server reads a hint comment up to the first problem and raises
 * ER_PARSE_ERROR as a warning; the statement runs with the hints before
 * the problem. Syntax: text the hint grammar does not take. ExecutionTime:
 * a MAX_EXECUTION_TIME limit above 4294967295 (but the numbers from 2^63
 * to 2^64 - 1). Size: a SET_VAR number above 2^64 - 1 (verified on a live
 * 8.4 server). Source:
 * https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-syntax.
 *
 * @visibility public
 * @example Reading the text of a failure
 *     \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintFailure::Syntax->value // => 'Optimizer hint syntax error'
 */
enum HintFailure: string
{
    case Syntax = 'Optimizer hint syntax error';
    case ExecutionTime = 'Unsupported MAX_EXECUTION_TIME';
    case Size = 'A size parameter was incorrectly specified, either number or on the form 10M';
}
