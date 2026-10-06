<?php

declare(strict_types=1);

namespace SqlFixture\Platform\MySql\Schema;

use SqlFixture\Analysis\CreateTableOperation;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\Exception\UnanalyzedColumnException;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition as WrittenColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
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
        foreach ($statement->elements as $element) {
            if (!$element instanceof WrittenColumn) {
                continue;
            }
            $column = $declared[$element->name->column->value] ?? throw new UnanalyzedColumnException($element->name->column->value);
            $columns[$column->name->value] = (new ColumnParser())->parse($element, $column, $primaryKeys);
        }

        return $columns;
    }

    /**
     * Collects the primary key columns declared on a column or as a table constraint, each once.
     *
     * @return list<string>
     */
    public function primaryKeys(Operation $operation, CreateTable $statement): array
    {
        $primaryKeys = [];
        foreach ($statement->elements as $element) {
            if ($element instanceof WrittenColumn && (new ColumnAttributes())->read($element->specification->columnAttributes())->primaryKey) {
                $primaryKeys[] = $element->name->column->value;
            } elseif ($element instanceof IndexDefinition && $element->kind === IndexKind::Primary) {
                foreach ($element->parts as $part) {
                    if ($part instanceof ColumnPart) {
                        $primaryKeys[] = (new CreateTableOperation())->columnName($operation, $part->column->value);
                    }
                }
            }
        }

        return array_values(array_unique($primaryKeys));
    }
}
