<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Column;

/**
 * Places an added, changed, or modified column before every other column.
 * @visibility public
 * @example Reading a FIRST placement
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ADD COLUMN n INT FIRST');
 *     $statement->alterations[0]->position // => \SqlSemantics\Model\Definition\MySqlTable\Column\FirstColumn::First
 */
enum FirstColumn: string
{
    case First = 'FIRST';
}
