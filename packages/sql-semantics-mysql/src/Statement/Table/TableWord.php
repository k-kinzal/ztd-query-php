<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

/**
 * The TABLE and TABLES alternatives of table lists, maintenance and locks.
 *
 * The alternatives name the same kind of object. Their written form remains
 * part of the statement so reconstruction does not choose a different keyword.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/flush.html.
 *
 * @visibility public
 * @example Selecting a table list keyword
 *     \SqlSemantics\Platform\MySql\Statement\Table\TableWord::Tables->value // => 'TABLES'
 */
enum TableWord: string
{
    case Table = 'TABLE';
    case Tables = 'TABLES';
}
