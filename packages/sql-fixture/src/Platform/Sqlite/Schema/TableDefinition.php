<?php

declare(strict_types=1);

namespace SqlFixture\Platform\Sqlite\Schema;

use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\Exception\UnanalyzedColumnException;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TablePrimaryKey;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;

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
        foreach ($statement->columns as $written) {
            $column = $declared[$written->name->value] ?? throw new UnanalyzedColumnException($written->name->value);
            $columns[$column->name->value] = (new ColumnParser())->parse($written, $column, $primaryKeys);
        }

        return $columns;
    }

    /**
     * Collects the primary key columns declared on a column or as a table constraint, each once.
     *
     * A table key term is resolved by the analysis, so a quoted name, a string
     * SQLite reads as a name and a collated term all name their column.
     *
     * @return list<string>
     */
    public function primaryKeys(Operation $operation, CreateTable $statement): array
    {
        $primaryKeys = [];
        foreach ($statement->columns as $written) {
            if ((new ColumnConstraints())->read($written->constraints)->primaryKey) {
                $primaryKeys[] = $written->name->value;
            }
        }
        foreach ($statement->constraints as $run) {
            foreach ($run->items as $constraint) {
                if (!$constraint instanceof TablePrimaryKey) {
                    continue;
                }
                foreach ($constraint->terms as $term) {
                    $resolution = $operation->facts->scalar($this->operand($term->expression))->resolution;
                    if ($resolution instanceof ResolvedColumn && $resolution->slot->column !== null) {
                        $primaryKeys[] = $resolution->slot->column->name->value;
                    }
                }
            }
        }

        return array_values(array_unique($primaryKeys));
    }

    /**
     * Returns the expression a key term names, without its parentheses and collations.
     */
    public function operand(Scalar $term): Scalar
    {
        while ($term instanceof Collate || $term instanceof Grouped) {
            $term = $term->operand;
        }

        return $term;
    }
}
