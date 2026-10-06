<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\ImplicitColumn;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Known;

/**
 * Derives the declaration a table or view definition provides.
 *
 * Rule: MYSQL-TABLE-DECLARATION-001. A CREATE TABLE declares one column per
 * column definition, in order, with the type as written and the NULL fact
 * of MYSQL-COLUMN-FLAGS-001; a column a table-level PRIMARY KEY names is NOT
 * NULL. A generated column (VIRTUAL or STORED) is an ordinary column for
 * reading and is declared generated: the server computes its value and
 * accepts only DEFAULT for it in a write. An INVISIBLE
 * column is found by name but not by `*` ("not part of SELECT *"): it is an
 * implicit column of the declaration.
 *
 * CREATE TABLE ... SELECT: "columns named only in the CREATE TABLE part come
 * first. Columns named in both parts or only in the SELECT part come after
 * that. The data type of SELECT columns can be overridden by also specifying
 * the column in the CREATE TABLE part." A column named in both parts takes
 * its name from the SELECT part and the rest from the CREATE TABLE part.
 * A column of the SELECT part takes the
 * name, the type and the NULL fact of its output field and is not
 * generated, also when the field reads a generated column; the column list
 * stops, and the declaration is incomplete, at the first field that has no
 * determined name or type or that an unexpanded star leaves open.
 *
 * CREATE VIEW: a view (RelationKind::View) with one column per output field,
 * named by the column list when one is written, with the type and NULL fact
 * of the field, never generated; incomplete under the same conditions and when the column
 * list has another length. Every other declaration is a base table.
 *
 * CREATE TABLE ... LIKE: the columns of the source table, as new
 * declarations with the same names, types, NULL facts and generation (the
 * copy keeps the generation expressions); an undeclared
 * source leaves the declaration empty and incomplete.
 * Terminates: one pass over the elements and the fields.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-like.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-view.html,
 * https://dev.mysql.com/doc/refman/8.4/en/invisible-columns.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableDeclaration
{
    /**
     * Answers the declaration of one column definition; `$keyed` tells whether a table-level primary key includes it.
     */
    public function column(ColumnDefinition $definition, bool $keyed = false): Column
    {
        $specification = $definition->specification;

        return new Column($definition->name->column, $specification->dataType(), (new ColumnFlags())->nullability($specification, $keyed), $specification instanceof GeneratedColumn);
    }

    /**
     * Answers the column names the table-level primary keys of a definition name.
     *
     * @return list<Name>
     */
    public function primaryColumns(CreateTable $definition): array
    {
        $names = [];
        foreach ($definition->elements as $element) {
            if ($element instanceof IndexDefinition && $element->kind === IndexKind::Primary) {
                foreach ($element->parts as $part) {
                    if ($part instanceof ColumnPart) {
                        $names[] = $part->column;
                    }
                }
            }
        }

        return $names;
    }

    /**
     * Builds the declaration of a CREATE TABLE; `$output` is the output of its query, when it has one.
     */
    public function table(CreateTable $definition, ?QueryFact $output, Derivation $derivation): Table
    {
        $comparison = $derivation->context->columnNames;
        $primary = $this->primaryColumns($definition);
        $defined = [];
        foreach ($definition->elements as $element) {
            if ($element instanceof ColumnDefinition) {
                $defined[] = [$element, $this->column($element, $this->named($element->name->column, $primary, $comparison))];
            }
        }
        $selected = [];
        $complete = true;
        $used = [];
        foreach ($output === null ? [] : $this->settled($output) as $field) {
            $match = $field->name === null ? null : $this->find($field->name, $defined, $comparison);
            $column = $match === null ? $this->fieldColumn($field, $field->name) : $this->renamed($defined[$match][1], $field->name);
            if ($column === null) {
                $complete = false;
                break;
            }
            if ($match !== null) {
                $used[$match] = true;
            }
            $selected[] = [$match === null ? null : $defined[$match][0], $column];
        }
        $complete = $complete && ($output === null || $output->shape->complete());
        $all = [];
        foreach ($defined as $position => $pair) {
            if (!isset($used[$position])) {
                $all[] = $pair;
            }
        }

        return $this->split($definition->name, [...$all, ...$selected], $derivation->context->profile, $complete);
    }

    /**
     * Builds a declaration from columns, keeping INVISIBLE columns as implicit columns.
     *
     * @param list<array{ColumnDefinition|null, Column}> $columns Each column with the definition it comes from, if any
     */
    public function split(QualifiedName $name, array $columns, LanguageProfile $profile, bool $complete): Table
    {
        $visible = [];
        $implicit = [];
        $flags = new ColumnFlags();
        foreach ($columns as [$definition, $column]) {
            if ($definition !== null && $flags->invisible($definition->specification)) {
                $implicit[] = new ImplicitColumn([$column->name], $column);
            } else {
                $visible[] = $column;
            }
        }

        return new Table($name, $profile, $visible, $implicit, $complete);
    }

    /**
     * Builds the declaration of a view from the output of its query and the names of its columns.
     *
     * @param list<Name|null> $names The name of each column: the column list when one is written, else the names the query gives
     * @param bool $listed Whether the names are a written column list, whose length must match the query
     */
    public function view(QualifiedName $name, QueryFact $output, array $names, bool $listed, LanguageProfile $profile): Table
    {
        $fields = $this->settled($output);
        $complete = $output->shape->complete() && (!$listed || count($names) === count($fields));
        $columns = [];
        foreach ($fields as $position => $field) {
            $column = $this->fieldColumn($field, $names[$position] ?? null);
            if ($column === null) {
                $complete = false;
                break;
            }
            $columns[] = $column;
        }

        return new Table($name, $profile, $columns, [], $complete, RelationKind::View);
    }

    /**
     * Builds the declaration of CREATE TABLE ... LIKE from the declaration of the source table, when it is declared.
     */
    public function like(QualifiedName $name, ?Table $source, LanguageProfile $profile): Table
    {
        if ($source === null) {
            return new Table($name, $profile, [], [], false);
        }
        $columns = [];
        foreach ($source->columns as $column) {
            $columns[] = new Column($column->name, $column->type, $column->nullability, $column->generated);
        }
        $implicit = [];
        foreach ($source->implicit as $hidden) {
            $implicit[] = new ImplicitColumn($hidden->names, new Column($hidden->column->name, $hidden->column->type, $hidden->column->nullability, $hidden->column->generated));
        }

        return new Table($name, $profile, $columns, $implicit, $source->complete);
    }

    /**
     * Answers a defined column under the name a field of the SELECT part gives it.
     */
    public function renamed(Column $column, Name $name): Column
    {
        return new Column($name, $column->type, $column->nullability, $column->generated);
    }

    /**
     * Answers the leading output fields of a query whose position is determined: those before the first unexpanded star.
     *
     * @return list<Field>
     */
    public function settled(QueryFact $output): array
    {
        $fields = [];
        foreach ($output->projection as $item) {
            if (!$item instanceof Field) {
                break;
            }
            $fields[] = $item;
        }

        return $fields;
    }

    /**
     * Answers the column an output field makes under a name, or null when its name or type is not determined.
     */
    public function fieldColumn(Field $field, ?Name $name): ?Column
    {
        return $name === null || !$field->type instanceof Known ? null : new Column($name, $field->type->descriptor, $field->nullability);
    }

    /**
     * Answers the position of the defined column with a name.
     *
     * @param list<array{ColumnDefinition, Column}> $defined
     */
    public function find(Name $name, array $defined, Comparison $comparison): ?int
    {
        foreach ($defined as $position => [$definition]) {
            if ($comparison->equal($definition->name->column->value, $name->value)) {
                return $position;
            }
        }

        return null;
    }

    /**
     * Tells whether a list of names holds a name.
     *
     * @param list<Name> $names
     */
    public function named(Name $name, array $names, Comparison $comparison): bool
    {
        foreach ($names as $candidate) {
            if ($comparison->equal($candidate->value, $name->value)) {
                return true;
            }
        }

        return false;
    }
}
