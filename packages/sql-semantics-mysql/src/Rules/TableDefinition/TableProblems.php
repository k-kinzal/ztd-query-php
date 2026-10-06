<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\GeneratedColumnWrite;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NullablePrimaryKey;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\QueryFact;

/**
 * Reports the problems of a table definition that the server rejects.
 *
 * Rule: MYSQL-TABLE-PROBLEMS-001. Diagnostics: two columns of the declared
 * table with one name (ER_DUP_FIELDNAME); more than one primary key, counting
 * each column with a PRIMARY KEY attribute and each PRIMARY KEY element
 * (ER_MULTIPLE_PRI_KEY); from MySQL 5.7 on, a primary key column whose NULL
 * attribute is still in force (ER_PRIMARY_CANT_HAVE_NULL; 5.6 makes it NOT
 * NULL silently); a key part or foreign key column that names no column of
 * the table, when its column list is complete (ER_KEY_COLUMN_DOES_NOT_EXITS);
 * a table without columns and without a query (ER_TABLE_MUST_HAVE_COLUMNS);
 * a column name that is not valid, among them the name a selected column
 * gets after its text (ER_WRONG_COLUMN_NAME, MYSQL-COLUMN-NAME-001); a
 * generated column of the CREATE TABLE part that a column of the SELECT
 * part names, so that the query would fill it
 * (ER_NON_DEFAULT_VALUE_FOR_GENERATED_COLUMN; sql/sql_insert.cc
 * `Query_result_create::create_table_for_query_block`).
 * Terminates: one pass over the elements and the key parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableProblems
{
    /**
     * Reports the problems of a definition against the declaration it provides.
     */
    public function report(CreateTable $definition, Table $table, Derivation $derivation): void
    {
        $this->duplicates($table, $derivation);
        $this->names($table, $derivation);
        $declaration = new TableDeclaration();
        $primary = $declaration->primaryColumns($definition);
        $keys = 0;
        $columns = 0;
        foreach ($definition->elements as $element) {
            if ($element instanceof ColumnDefinition) {
                $columns++;
                [$notNull, $explicit, $attribute] = (new ColumnFlags())->flags($element->specification);
                $keys += $attribute ? 1 : 0;
                $keyed = $attribute || $declaration->named($element->name->column, $primary, $derivation->context->columnNames);
                if ($keyed && $explicit && !$notNull && $derivation->context->profile->grammar !== GrammarRelease::MySql5651) {
                    $derivation->report(new NullablePrimaryKey($element->name->column));
                }
            }
            $keys += $element instanceof IndexDefinition && $element->kind === IndexKind::Primary ? 1 : 0;
            $this->keyColumns($element instanceof IndexDefinition ? $element->parts : ($element instanceof ForeignKey ? $element->columns : []), $table, $derivation);
        }
        if ($keys > 1) {
            $derivation->report(new MultiplePrimaryKeys());
        }
        if ($columns === 0 && $definition->query === null) {
            $derivation->report(new NoColumns());
        }
    }

    /**
     * Reports each column name that is not a valid column name (MYSQL-COLUMN-NAME-001).
     */
    public function names(Table $table, Derivation $derivation): void
    {
        $rule = new ColumnNameRule();
        foreach ([...$table->columns, ...array_map(static fn ($implicit) => $implicit->column, $table->implicit)] as $column) {
            if (!$rule->valid($column->name->value)) {
                $derivation->report(new IncorrectColumnName($column->name));
            }
        }
    }

    /**
     * Reports each column name the declaration repeats.
     */
    public function duplicates(Table $table, Derivation $derivation): void
    {
        $seen = [];
        $all = $table->columns;
        foreach ($table->implicit as $implicit) {
            $all[] = $implicit->column;
        }
        foreach ($all as $column) {
            if ((new TableDeclaration())->named($column->name, $seen, $derivation->context->columnNames)) {
                $derivation->report(new DuplicateColumn($column->name));
            }
            $seen[] = $column->name;
        }
    }

    /**
     * Reports each generated column of the CREATE TABLE part that a column of the SELECT part would fill (MYSQL-GENERATED-WRITE-001).
     */
    public function selected(CreateTable $definition, QueryFact $output, Derivation $derivation): void
    {
        $comparison = $derivation->context->columnNames;
        foreach ((new TableDeclaration())->settled($output) as $field) {
            foreach ($definition->elements as $element) {
                if ($field->name !== null && $element instanceof ColumnDefinition && $element->specification instanceof GeneratedColumn && $comparison->equal($element->name->column->value, $field->name->value)) {
                    $derivation->report(new GeneratedColumnWrite($field->name, $definition->name->name));
                }
            }
        }
    }

    /**
     * Reports the key parts that name no column of a complete table.
     *
     * @param list<object> $parts The key parts
     */
    public function keyColumns(array $parts, Table $table, Derivation $derivation): void
    {
        if (!$table->complete) {
            return;
        }
        foreach ($parts as $part) {
            if ($part instanceof ColumnPart && $table->matchingColumns($part->column->value, $derivation->context->columnNames) === []) {
                $derivation->report(new UnknownKeyColumn($part->column));
            }
        }
    }
}
