<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

/**
 * What ALTER TABLESPACE does with a data file: ADD, DROP or, in MySQL 5.x, CHANGE.
 *
 * Mirrors the ts_command_type of the alter forms. Each case holds its
 * keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-tablespace.html,
 * https://dev.mysql.com/doc/refman/5.7/en/alter-tablespace.html.
 *
 * @visibility public
 * @example Reading the keyword of a case
 *     \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\DatafileAction::Drop->value // => 'DROP'
 */
enum DatafileAction: string
{
    case Add = 'ADD';
    case Drop = 'DROP';
    case Change = 'CHANGE';
}
