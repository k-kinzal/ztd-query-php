<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\Exception\UnanalyzedColumnException;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition as WrittenColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Operation;

/**
 * Reads the columns and primary key of a CREATE TABLE statement.
 *
 * @visibility root
 */
final class TableDefinition
{
    /**
     * Returns the written columns, keyed by name, as the analysis declares them.
     *
     * @param array<string, Column> $declared
     * @param list<string> $primaryKeys
     * @return array<string, ColumnDefinition>
     * @throws UnanalyzedColumnException When the analysis declares no column for a written one
     */
    public function columns(CreateTable $statement, array $declared, array $primaryKeys): array
    {
        $columns = [];
        foreach ($this->elements($statement) as $element) {
            if (!$element instanceof WrittenColumn) {
                continue;
            }
            $column = $declared[$element->name->value] ?? throw new UnanalyzedColumnException($element->name->value);
            $columns[$column->name->value] = (new ColumnParser())->parse($element, $column, $primaryKeys);
        }

        return $columns;
    }

    /**
     * Collects the primary key columns declared on a column or as a table constraint, without included columns, each once.
     *
     * @return list<string>
     */
    public function primaryKeys(Operation $operation, CreateTable $statement): array
    {
        $primaryKeys = [];
        foreach ($this->elements($statement) as $element) {
            if ($element instanceof WrittenColumn && (new ColumnConstraints())->read($element->qualifiers)->primaryKey) {
                $primaryKeys[] = $element->name->value;
            } elseif ($element instanceof TablePrimaryKey) {
                foreach ($element->columns as $column) {
                    $primaryKeys[] = (new CreateTableOperation())->columnName($operation, $column->value);
                }
            }
        }

        return array_values(array_unique($primaryKeys));
    }

    /**
     * Returns the columns and table constraints of a statement that lists them.
     *
     * @return list<object>
     */
    public function elements(CreateTable $statement): array
    {
        return $statement->definition instanceof ListedColumns ? $statement->definition->elements() : [];
    }
}
