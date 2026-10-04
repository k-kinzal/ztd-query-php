<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

/**
 * The direction of a procedure parameter: IN, OUT or INOUT.
 *
 * Each case holds its keyword. A parameter written without a keyword is an
 * IN parameter; the model keeps whether the keyword is written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility public
 * @example Reading the keyword of a mode
 *     \SqlSemantics\Platform\MySql\Statement\Routine\ParameterMode::InOut->value // => 'INOUT'
 */
enum ParameterMode: string
{
    case In = 'IN';
    case Out = 'OUT';
    case InOut = 'INOUT';
}
