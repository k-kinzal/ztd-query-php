<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Column;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Renames a column without changing its declaration (MySQL 8.0 and later).
 * @visibility public
 * @example Renaming a column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t RENAME COLUMN id TO code');
 *     [$statement->alterations[0]->column, $statement->alterations[0]->newName] // => ['id', 'code']
 */
final class RenameColumn implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly string $newName)
    {
        AlterationInvariant::name($column);
        AlterationInvariant::name($newName);
    }
}
