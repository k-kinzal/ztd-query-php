<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Condition;

/**
 * The classes of SQLSTATE values a handler can name: SQLWARNING, NOT FOUND and SQLEXCEPTION.
 *
 * SQLWARNING stands for the values that begin with `01`, NOT FOUND for
 * those that begin with `02`, SQLEXCEPTION for every other value that does
 * not begin with `00`.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-handler.html.
 *
 * @visibility public
 * @example Listing the classes
 *     count(\SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionClass::cases()) // => 3
 */
enum ConditionClass
{
    case SqlWarning;
    case NotFound;
    case SqlException;
}
