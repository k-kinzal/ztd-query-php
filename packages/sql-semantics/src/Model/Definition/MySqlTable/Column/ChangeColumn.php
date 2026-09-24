<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Column;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableConstraint;

/**
 * Replaces the declaration of a named column, possibly under a new name (CHANGE COLUMN).
 * @visibility public
 * @example Renaming and redeclaring a column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t CHANGE COLUMN n total BIGINT NOT NULL FIRST');
 *     $statement->alterations[0]->column // => 'n'
 *     $statement->alterations[0]->definition->name // => 'total'
 *     $statement->alterations[0]->position // => \SqlSemantics\Model\Definition\MySqlTable\Column\FirstColumn::First
 */
final class ChangeColumn implements TableAlteration
{
    /**
     * @param list<TableConstraint> $constraints Constraints written on the new declaration
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly ColumnDefinition $definition, public readonly array $constraints = [], public readonly FirstColumn|AfterColumn|null $position = null)
    {
        AlterationInvariant::name($column);
        AlterationInvariant::column($definition, $constraints);
    }
}
