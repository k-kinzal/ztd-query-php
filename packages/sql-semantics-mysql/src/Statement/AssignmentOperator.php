<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement;

/**
 * The written operator of a variable, column or option assignment.
 *
 * Both signs assign the same value. Optional option assignments use null
 * when no sign is written; the operator never contains input SQL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/assignment-operators.html.
 *
 * @visibility public
 * @example Selecting colon assignment
 *     \SqlSemantics\Platform\MySql\Statement\AssignmentOperator::ColonEqual->value // => ':='
 */
enum AssignmentOperator: string
{
    case Equal = '=';
    case ColonEqual = ':=';
}
