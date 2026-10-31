<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

/**
 * The synonymous KEY and INDEX keywords in definitions, hints and index lists.
 *
 * Their index semantics agree; the written keyword remains part of the SQL
 * structure so that reconstruction preserves the requested form.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/index-hints.html.
 *
 * @visibility public
 * @example Selecting the KEY spelling of an index hint
 *     \SqlSemantics\Platform\MySql\Statement\Table\IndexWord::Key->value // => 'KEY'
 */
enum IndexWord: string
{
    case Key = 'KEY';
    case Index = 'INDEX';
}
