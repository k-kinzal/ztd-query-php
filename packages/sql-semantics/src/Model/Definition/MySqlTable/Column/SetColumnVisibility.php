<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Column;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Makes a column visible or invisible to SELECT * (MySQL 8.0 and later).
 * @visibility public
 * @example Hiding a column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ALTER COLUMN n SET INVISIBLE');
 *     $statement->alterations[0]->visible // => false
 */
final class SetColumnVisibility implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly bool $visible)
    {
        AlterationInvariant::name($column);
    }
}
