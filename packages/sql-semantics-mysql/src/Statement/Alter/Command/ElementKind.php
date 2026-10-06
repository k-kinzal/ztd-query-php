<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

/**
 * The kinds of table elements ALTER TABLE drops, renames or alters by name.
 *
 * Mirrors Alter_drop::drop_type: a column, the primary key, a foreign key,
 * an index (KEY and INDEX are synonyms), a check constraint, and a
 * constraint of any kind (8.0.19 and later). Each case holds the keywords
 * it is written with.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility public
 * @example Reading the keywords of a case
 *     \SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind::ForeignKey->value // => 'FOREIGN KEY'
 */
enum ElementKind: string
{
    case Column = 'COLUMN';
    case PrimaryKey = 'PRIMARY KEY';
    case ForeignKey = 'FOREIGN KEY';
    case Index = 'INDEX';
    case Check = 'CHECK';
    case Constraint = 'CONSTRAINT';
}
