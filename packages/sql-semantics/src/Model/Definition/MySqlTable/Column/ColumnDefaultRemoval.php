<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Column;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Removes the default of a column.
 * @visibility public
 * @example Dropping a default
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT DEFAULT 1)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ALTER id DROP DEFAULT');
 *     $statement->alterations[0]->column // => 'id'
 */
final class ColumnDefaultRemoval implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column)
    {
        AlterationInvariant::name($column);
    }
}
