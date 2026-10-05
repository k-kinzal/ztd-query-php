<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\SystemColumns;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\IndexConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateForeignTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\LikeClause;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\PartitionOf;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\TableForm;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Derives CREATE TABLE and CREATE FOREIGN TABLE.
 *
 * Rule: PG-TABLE-DEFINITION-001. The new table is named as written; a
 * temporary table with an unqualified name belongs to the temporary schema
 * `pg_temp`, and inside CREATE SCHEMA an unqualified table belongs to the
 * schema being created. The statement provides the declaration of
 * PG-TABLE-DECLARATION-001 and records it as its own relation fact. The
 * parents of INHERITS and PARTITION OF are resolved (PG-TABLE-TARGET-001).
 * Every expression of the definition (constraints, generated columns,
 * partition keys) is derived where the new table is the only visible
 * relation, its system columns included; defaults and partition bound values
 * see no column. Problems: a column name written twice, a column named like
 * a system column, more than one primary key, an array of a serial type.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html. Termination:
 * one pass over the elements. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Definitions
{
    /**
     * Provides the declaration and derives the definition; `$schema` is the schema of an enclosing CREATE SCHEMA.
     */
    public function derive(CreateTable|CreateForeignTable $create, Derivation $derivation, ?Name $schema): void
    {
        $name = $this->name($create, $schema);
        $table = (new Declarations())->table($create->definition, $derivation, $name);
        $derivation->declare($table);
        $targets = new Targets();
        $fact = $derivation->target($create, new RelationFact($targets->shape($table), new DeclaredTable($table)));
        $scope = $targets->scope($derivation, $create, $name, $fact->shape, $targets->implicit($fact));
        $this->report($create->definition, $derivation, $name);
        if ($create instanceof CreateTable) {
            (new CreationSchemas())->check($derivation, $create->name, $create->persistence);
        }
        $this->elements($create->definition, $derivation, $scope);
        if ($create instanceof CreateTable) {
            $create->partitioning?->deriveClause($derivation, $scope);
            foreach ($create->options as $option) {
                $option->deriveClause($derivation, $scope);
            }
        }
    }

    /**
     * Answers the facts of the new table without providing its declaration.
     */
    public function fact(CreateTable|CreateForeignTable $create, Derivation $derivation, ?Name $schema): RelationFact
    {
        $table = (new Declarations())->table($create->definition, $derivation, $this->name($create, $schema));

        return new RelationFact((new Targets())->shape($table), new DeclaredTable($table));
    }

    /**
     * Answers the name the new table is declared under.
     */
    public function name(CreateTable|CreateForeignTable $create, ?Name $schema): QualifiedName
    {
        $written = $create->name;
        if ($written->schema !== null) {
            return $written;
        }
        if ($create instanceof CreateTable && $create->persistence->temporary()) {
            return new QualifiedName($written->name, new Name('pg_temp'));
        }

        return $schema === null ? $written : new QualifiedName($written->name, $schema);
    }

    /**
     * Resolves the parents and derives the elements and the partition bound.
     */
    public function elements(TableForm $form, Derivation $derivation, Environment $scope): void
    {
        $targets = new Targets();
        foreach ($form instanceof ListedColumns ? $form->parents : [] as $parent) {
            $derivation->target($parent, $targets->resolve($derivation, $parent->name));
        }
        if ($form instanceof PartitionOf) {
            $derivation->target($form->parent, $targets->resolve($derivation, $form->parent->name));
            $form->bound->deriveClause($derivation, $scope);
        }
        foreach ($form->elements() as $element) {
            $element->deriveClause($derivation, $scope);
        }
    }

    /**
     * Reports repeated column names (the columns a LIKE clause copies included), system column names, several primary keys and serial arrays.
     */
    public function report(TableForm $form, Derivation $derivation, QualifiedName $name): void
    {
        $seen = [];
        $keys = 0;
        $system = new SystemColumns();
        foreach ($form->elements() as $element) {
            $resolution = $element instanceof LikeClause ? $derivation->table($element->table, $derivation->environment()) : null;
            foreach ($resolution instanceof DeclaredTable && $resolution->table->complete ? $resolution->table->columns : [] as $column) {
                if (isset($seen[$column->name->value])) {
                    $derivation->report(new DefinitionProblem(DefinitionRule::DuplicateColumn, $column->name));
                }
                $seen[$column->name->value] = true;
            }
            if ($element instanceof ColumnDefinition || $element instanceof ColumnOptions) {
                if (isset($seen[$element->name->value])) {
                    $derivation->report(new DefinitionProblem(DefinitionRule::DuplicateColumn, $element->name));
                }
                $seen[$element->name->value] = true;
                if ($system->reserved($element->name)) {
                    $derivation->report(new DefinitionProblem(DefinitionRule::SystemColumnName, $element->name));
                }
                foreach ($element->qualifiers as $qualifier) {
                    $keys += $qualifier instanceof ColumnPrimaryKey ? 1 : 0;
                }
            }
            if ($element instanceof ColumnDefinition && $element->type->array !== null && (new ColumnTyping())->serial($element->type) !== null) {
                $derivation->report(new DefinitionProblem(DefinitionRule::SerialArray));
            }
            $keys += $element instanceof TablePrimaryKey || ($element instanceof IndexConstraint && $element->primary) ? 1 : 0;
        }
        if ($keys > 1) {
            $derivation->report(new DefinitionProblem(DefinitionRule::MultiplePrimaryKeys, $name->name));
        }
    }
}
