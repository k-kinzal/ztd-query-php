<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Column;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableConstraint;

/**
 * Adds one column with the integrity constraints written on it, optionally at a position.
 * @visibility public
 * @example Reading the added MySQL column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ADD COLUMN n INT NOT NULL UNIQUE AFTER id');
 *     $statement->alterations[0]->column->name // => 'n'
 *     count($statement->alterations[0]->constraints) // => 1
 *     $statement->alterations[0]->position->column // => 'id'
 */
final class AddColumn implements TableAlteration
{
    /**
     * @param list<TableConstraint> $constraints Constraints written on the column
     * @throws InvalidStructure
     */
    public function __construct(public readonly ColumnDefinition $column, public readonly array $constraints = [], public readonly FirstColumn|AfterColumn|null $position = null)
    {
        AlterationInvariant::column($column, $constraints);
    }
}
