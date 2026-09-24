<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Column;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableConstraint;

/**
 * Replaces the declaration of a column while keeping its name (MODIFY COLUMN).
 * @visibility public
 * @example Redeclaring a column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t MODIFY n VARCHAR(20) AFTER id');
 *     $statement->alterations[0]->definition->name // => 'n'
 *     $statement->alterations[0]->position->column // => 'id'
 */
final class ModifyColumn implements TableAlteration
{
    /**
     * @param list<TableConstraint> $constraints Constraints written on the declaration
     * @throws InvalidStructure
     */
    public function __construct(public readonly ColumnDefinition $definition, public readonly array $constraints = [], public readonly FirstColumn|AfterColumn|null $position = null)
    {
        AlterationInvariant::column($definition, $constraints);
    }
}
