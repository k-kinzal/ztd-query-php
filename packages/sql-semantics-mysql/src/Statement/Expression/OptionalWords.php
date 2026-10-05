<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

/**
 * Whether optional words that do not change the meaning are written, such as the empty parentheses after CURDATE or the ROW before a row constructor.
 *
 * Both forms mean the same. The form is kept because MySQL names an
 * unaliased select list expression after its text, so `SELECT CURDATE` and
 * `SELECT CURDATE()` return differently named columns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html.
 *
 * @visibility public
 * @example Reading whether the parentheses are written
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT CURRENT_DATE');
 *     $query->statement->items[0]->expression->parentheses // => \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted
 */
enum OptionalWords
{
    case Written;
    case Omitted;
}
