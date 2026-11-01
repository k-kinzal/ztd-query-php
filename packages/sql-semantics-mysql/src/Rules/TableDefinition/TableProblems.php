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
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Reports the problems of a table definition that the server rejects.
 *
 * Rule: MYSQL-TABLE-PROBLEMS-001. Diagnostics: two columns of the declared
 * table with one name (ER_DUP_FIELDNAME); more than one primary key, counting
 * each column with a PRIMARY KEY attribute and each PRIMARY KEY element
 * (ER_MULTIPLE_PRI_KEY); from MySQL 5.7 on, a primary key column with any explicit NULL
 * attribute (ER_PRIMARY_CANT_HAVE_NULL; 5.6 makes it NOT
 * NULL silently); a key part or foreign key column that names no column of
 * the table, when its column list is complete (ER_KEY_COLUMN_DOES_NOT_EXITS);
 * an index that names one column twice, reported at the second part that
 * names it (ER_DUP_FIELDNAME);
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
        $this->collations($definition->elements, $derivation);
        $this->duplicates($table, $derivation);
        $this->names($table, $derivation);
        foreach ($definition->elements as $element) {
            $this->keyColumns($element instanceof IndexDefinition ? $element->parts : ($element instanceof ForeignKey ? $element->columns : []), $table, $derivation, $element instanceof IndexDefinition);
        }
    }

    /**
     * Answers the first problem of a written definition the server reports, in the order it
     * checks a definition it executes: a column name written twice (ER_DUP_FIELDNAME), then from
     * 5.7 on a primary key column with an explicit NULL attribute (ER_PRIMARY_CANT_HAVE_NULL), then a
     * second PRIMARY KEY attribute (ER_MULTIPLE_PRI_KEY); on MySQL 5.6, 5.7 and from 9.0 on a
     * definition without columns and without a query (ER_TABLE_MUST_HAVE_COLUMNS) before its
     * keys, on 8.x after them; then each key element in order: on 5.x a key column no written
     * column has (ER_KEY_COLUMN_DOES_NOT_EXITS) before a second primary key, on 8.0 and later
     * the second primary key first. Key columns are checked against the written columns only
     * when no query supplies columns (verified on 8.4.7, 5.7.44 and 5.6.51). Null when the
     * definition has none of these problems; the problems of a query's columns and of a routine
     * body are not written problems. A spatial key with more than one part (ER_TOO_MANY_KEY_PARTS)
     * and a primary, full-text or spatial key with a functional part (ER_FUNCTIONAL_INDEX_PRIMARY_KEY
     * and its kin) are refused before the parts of the key. Within a key the parts are checked in order: a part naming
     * no written column (ER_KEY_COLUMN_DOES_NOT_EXITS), or a part naming a column an earlier part
     * of the same key named (ER_DUP_FIELDNAME), whichever comes first.
     *
     * @param list<TableElement> $elements The written elements
     * @param GrammarRelease $release The release whose order of checks applies
     */
    public function problem(array $elements, ?TableQuery $query, GrammarRelease $release): ?Diagnostic
    {
        $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $emptyFirst = $legacy || in_array($release, [GrammarRelease::MySql901, GrammarRelease::MySql910], true);
        $columns = [];
        $primaryAttributes = 0;
        $primaryParts = [];
        foreach ($elements as $element) {
            if ($element instanceof IndexDefinition && $element->kind === IndexKind::Primary) {
                foreach ($element->parts as $part) {
                    if ($part instanceof ColumnPart) {
                        $primaryParts[] = $part->column;
                    }
                }
            }
        }
        $nullable = null;
        foreach ($elements as $element) {
            if (!$element instanceof ColumnDefinition) {
                continue;
            }
            foreach ($columns as $column) {
                if (mb_strtolower($column->value, 'UTF-8') === mb_strtolower($element->name->column->value, 'UTF-8')) {
                    return new DuplicateColumn($element->name->column);
                }
            }
            $columns[] = $element->name->column;
            [, $explicit, $attribute] = (new ColumnFlags())->flags($element->specification);
            $primaryAttributes += $attribute ? 1 : 0;
            if ($explicit && ($attribute || $this->written($element->name->column, $primaryParts)) && $release !== GrammarRelease::MySql5651) {
                $nullable ??= new NullablePrimaryKey($element->name->column);
            }
        }
        if ($nullable !== null) {
            return $nullable;
        }
        if ($primaryAttributes > 1) {
            return new MultiplePrimaryKeys();
        }
        if ($emptyFirst && $columns === [] && $query === null) {
            return new NoColumns();
        }
        $primaryKeys = $primaryAttributes;
        foreach ($elements as $element) {
            if (!$element instanceof IndexDefinition && !$element instanceof ForeignKey) {
                continue;
            }
            $primary = $element instanceof IndexDefinition && $element->kind === IndexKind::Primary;
            if (!$legacy && $primary && ++$primaryKeys > 1) {
                return new MultiplePrimaryKeys();
            }
            if ($element instanceof IndexDefinition && $element->kind === IndexKind::Spatial && count($element->parts) > 1) {
                return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\SpatialKeyArity($element->parts[0]);
            }
            if ($element instanceof IndexDefinition && in_array($element->kind, [IndexKind::Primary, IndexKind::FullText, IndexKind::Spatial], true)) {
                foreach ($element->parts as $part) {
                    if (!$part instanceof ColumnPart) {
                        return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\FunctionalIndexKind($element->kind, $part);
                    }
                }
            }
            $seen = [];
            foreach ($element instanceof IndexDefinition ? $element->parts : $element->columns as $part) {
                if (!$part instanceof ColumnPart) {
                    continue;
                }
                if ($query === null && !$this->written($part->column, $columns)) {
                    return new UnknownKeyColumn($part->column);
                }
                if ($this->written($part->column, $seen)) {
                    return new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\DuplicateKeyColumn($part->column);
                }
                $seen[] = $part->column;
            }
            if ($legacy && $primary && ++$primaryKeys > 1) {
                return new MultiplePrimaryKeys();
            }
        }
        if (!$emptyFirst && $columns === [] && $query === null) {
            return new NoColumns();
        }

        return null;
    }

    /**
     * Tells whether a written column list holds a name, compared as MySQL compares column names.
     *
     * @param list<Name> $columns
     */
    public function written(Name $name, array $columns): bool
    {
        foreach ($columns as $column) {
            if (mb_strtolower($column->value, 'UTF-8') === mb_strtolower($name->value, 'UTF-8')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports explicit column collations that the selected release does not provide.
     *
     * @param list<TableElement> $elements
     */
    public function collations(array $elements, Derivation $derivation): void
    {
        $names = [];
        foreach ($elements as $element) {
            if (!$element instanceof ColumnDefinition) {
                continue;
            }
            $specification = $element->specification;
            if ($specification instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn || ($specification instanceof GeneratedColumn || $specification instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn)) {
                foreach ($specification->attributes as $attribute) {
                    if ($attribute instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\CollateAttribute) {
                        $names[] = $attribute->collation;
                    }
                }
            }
            if (($specification instanceof GeneratedColumn || $specification instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn)) {
                $names[] = $specification->collation;
            }
        }
        foreach ($names as $collation) {
            $name = $collation?->name;
            if ($name !== null && !(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named($name->value)?->availableIn($derivation->context->profile->grammar) ?? false)) {
                $problem = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation($name->value);
                $derivation->report($problem);
                $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true), $collation);
            }
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
                if ($field->name !== null && $element instanceof ColumnDefinition && ($element->specification instanceof GeneratedColumn || $element->specification instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\Generation\DeferredGeneratedColumn) && $comparison->equal($element->name->column->value, $field->name->value)) {
                    $derivation->report(new GeneratedColumnWrite($field->name, $definition->name->name));
                }
            }
        }
    }

    /**
     * Reports the key parts that name no column of a complete table, and the first column an index names twice.
     *
     * @param list<object> $parts The key parts
     * @param bool $index Whether the parts are those of an index, which names each column once
     */
    public function keyColumns(array $parts, Table $table, Derivation $derivation, bool $index = false): void
    {
        $seen = [];
        foreach ($parts as $part) {
            if (!$part instanceof ColumnPart) {
                continue;
            }
            if ($table->complete && $table->matchingColumns($part->column->value, $derivation->context->columnNames) === []) {
                $derivation->report(new UnknownKeyColumn($part->column));
            } elseif ($index && (new TableDeclaration())->named($part->column, $seen, $derivation->context->columnNames)) {
                $derivation->report(new \SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\DuplicateKeyColumn($part->column));

                return;
            }
            $seen[] = $part->column;
        }
    }
}
