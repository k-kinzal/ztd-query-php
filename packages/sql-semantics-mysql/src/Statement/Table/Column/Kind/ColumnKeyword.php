<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column\Kind;

/**
 * The column attributes written as keywords alone: NULL, NOT NULL, AUTO_INCREMENT, keys, visibility and NOT SECONDARY.
 *
 * Each case holds the keywords it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnKeyword::NotNull->value // => 'NOT NULL'
 */
enum ColumnKeyword: string
{
    case Null = 'NULL';
    case NotNull = 'NOT NULL';
    case NotSecondary = 'NOT SECONDARY';
    case AutoIncrement = 'AUTO_INCREMENT';
    case SerialDefaultValue = 'SERIAL DEFAULT VALUE';
    case PrimaryKey = 'PRIMARY KEY';
    case Unique = 'UNIQUE';
    case Visible = 'VISIBLE';
    case Invisible = 'INVISIBLE';
}
