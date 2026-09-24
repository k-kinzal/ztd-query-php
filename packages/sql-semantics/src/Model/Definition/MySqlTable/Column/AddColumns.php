<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Column;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\Schema\TableConstraint;

/**
 * Adds a parenthesized list of columns, together with the constraints and indexes declared in the list.
 * @visibility public
 * @example Reading the added columns
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ADD (a INT, b INT, KEY ix (a))');
 *     array_map(static fn ($column) => $column->name, $statement->alterations[0]->columns) // => ['a', 'b']
 *     $statement->alterations[0]->indexes[0]->name // => 'ix'
 * @example Rejecting an empty column list
 *     new \SqlSemantics\Model\Definition\MySqlTable\Column\AddColumns([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class AddColumns implements TableAlteration
{
    /**
     * @param non-empty-list<ColumnDefinition> $columns Added columns in declaration order
     * @param list<TableConstraint> $constraints Constraints declared on the columns or in the list
     * @param list<IndexDefinition> $indexes Indexes declared in the list
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $columns, public readonly array $constraints = [], public readonly array $indexes = [])
    {
        Collections::objects(Collections::nonEmpty($columns), ColumnDefinition::class);
        foreach ($columns as $column) {
            AlterationInvariant::column($column, $constraints);
        }
        Collections::objects($indexes, IndexDefinition::class);
    }
}
