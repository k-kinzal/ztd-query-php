<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable;

/**
 * One change requested by a MySQL ALTER TABLE statement; each alteration form owns exactly its operands.
 * @visibility public
 * @example Reading an ordered alteration list
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ADD COLUMN n INT, DROP COLUMN id');
 *     $statement->alterations[1] instanceof \SqlSemantics\Model\Definition\MySqlTable\TableAlteration // => true
 */
interface TableAlteration
{
}
