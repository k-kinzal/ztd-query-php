<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;

/**
 * Derives the row shape of a table a definition names or defines, and the scope of the expressions inside a definition.
 *
 * Rule: MYSQL-DEFINITION-SCOPE-001. A name resolves by CORE-TABLE-LOOKUP-001
 * in the current database or the database written; no common table is in
 * scope of a definition. A declared table contributes one slot per declared
 * column, in order, each referring to the declaration; its implicit columns
 * (the INVISIBLE columns, MYSQL-TABLE-DECLARATION-001) are found by name
 * only. An undeclared or conditionally resolved name contributes an open
 * shape that names the missing declaration; a missing or conflicting name
 * an empty complete shape and the diagnostic. A foreign key that names the
 * table being defined refers to the declaration the statement provides
 * (create-table-foreign-keys.html: "a foreign key can reference the same
 * table"). The expressions of a definition (DEFAULT expressions, generated
 * column expressions, CHECK conditions and functional key parts) see the
 * columns of the table being defined or indexed, unqualified or qualified by
 * the table name, and nothing else. Terminates: one pass over the columns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html,
 * https://dev.mysql.com/doc/refman/8.4/en/invisible-columns.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TableTargets
{
    /**
     * Answers the row shape of a declaration: one slot per declared column.
     */
    public function shape(Table $table): RowShape
    {
        $slots = [];
        foreach ($table->columns as $column) {
            $slots[] = new OutputSlot($column->name, new Known($column->type), $column->nullability, $column);
        }

        return new RowShape($slots, $table->complete ? [] : [new IncompleteMembers($table)]);
    }

    /**
     * Answers the slots found by the implicit column names of a declaration.
     *
     * @return list<ImplicitSlot>
     */
    public function implicit(Table $table): array
    {
        $slots = [];
        foreach ($table->implicit as $implicit) {
            $slots[] = new ImplicitSlot($implicit->names, new OutputSlot($implicit->names[0], new Known($implicit->column->type), $implicit->column->nullability, $implicit->column));
        }

        return $slots;
    }

    /**
     * Resolves the name of an existing table and answers its facts.
     */
    public function existing(Derivation $derivation, QualifiedName $name): RelationFact
    {
        $resolution = $derivation->table($name, $derivation->environment());
        if ($resolution instanceof DeclaredTable) {
            return new RelationFact($this->shape($resolution->table), $resolution);
        }
        if ($resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable) {
            return new RelationFact(new RowShape([], [$resolution->missing]), $resolution);
        }

        return new RelationFact(new RowShape([]), $resolution);
    }

    /**
     * Resolves the parent table of a reference: the table being defined when the reference names it, otherwise an existing table.
     *
     * @param Table|null $defined The declaration of the table being defined
     * @param QualifiedName|null $definedName The name the statement writes for that table
     */
    public function parent(Derivation $derivation, QualifiedName $name, ?Table $defined, ?QualifiedName $definedName): RelationFact
    {
        $names = $derivation->context->relationNames;
        if ($defined !== null && $definedName !== null && $names->equal($name->name->value, $definedName->name->value)
            && ($name->schema === null ? $definedName->schema === null : $definedName->schema !== null && $names->equal($name->schema->value, $definedName->schema->value))) {
            return new RelationFact($this->shape($defined), new DeclaredTable($defined));
        }

        return $this->existing($derivation, $name);
    }

    /**
     * Answers the position inside a definition: its only visible relation is the table, found by its name.
     *
     * @param Relation $relation The statement node that stands for the table
     * @param QualifiedName $name The name that qualifies its columns
     * @param RowShape $shape The row shape of the table
     * @param list<ImplicitSlot> $implicit The slots found by name only
     */
    public function scope(Derivation $derivation, Relation $relation, QualifiedName $name, RowShape $shape, array $implicit = []): Environment
    {
        return new Environment($derivation->context, null, [new VisibleRelation($relation, $shape, null, $name, [], $implicit)]);
    }
}
