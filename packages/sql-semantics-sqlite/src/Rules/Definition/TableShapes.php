<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Definition;

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
 * Derives the row shape of the table a definition or administration command names, and the scope of its expressions.
 *
 * Rule: SQLITE-DEFINITION-TARGET-001. The name resolves by
 * CORE-TABLE-LOOKUP-001. A declared table contributes one slot per declared
 * column, in order, each referring to the declaration, and an open shape
 * when its column list is incomplete; an undeclared or conditionally
 * resolved name contributes an open shape that names the missing
 * declaration; a missing or conflicting name contributes an empty complete
 * shape and is a diagnostic. The implicit columns of a declared table (the
 * row identifier names) are found by name only and refer to the slot of the
 * declared column they alias, when they alias one. Precision: every slot of a
 * declared table is a known type. Terminates: one pass over the columns.
 * Source: https://sqlite.org/lang_naming.html, https://sqlite.org/lang_createtable.html#rowid.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TableShapes
{
    /**
     * Answers the row shape of a declaration.
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
     * Answers the slots found by the implicit column names of a declaration; `$shape` must be its row shape.
     *
     * @return list<ImplicitSlot>
     */
    public function implicit(Table $table, RowShape $shape): array
    {
        $slots = [];
        foreach ($table->implicit as $implicit) {
            $position = array_search($implicit->column, $table->columns, true);
            $slots[] = new ImplicitSlot(
                $implicit->names,
                is_int($position) ? $shape->slots[$position] : new OutputSlot($implicit->names[0], new Known($implicit->column->type), $implicit->column->nullability, $implicit->column),
            );
        }

        return $slots;
    }

    /**
     * Resolves the name of an existing table and answers its facts.
     */
    public function target(Derivation $derivation, QualifiedName $name): RelationFact
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
     * Answers the implicit slots of the table a relation fact resolved to, if it resolved to one declaration.
     *
     * @return list<ImplicitSlot>
     */
    public function implicitOf(RelationFact $fact): array
    {
        return $fact->table instanceof DeclaredTable ? $this->implicit($fact->table->table, new RowShape(array_slice($fact->shape->slots, 0, count($fact->table->table->columns)))) : [];
    }

    /**
     * Answers the scope of the expressions of a definition whose only visible relation is the given table.
     *
     * @param Relation $relation The statement node that stands for the table
     * @param QualifiedName $name The name that qualifies its columns
     * @param RowShape $shape The row shape of the table
     * @param list<ImplicitSlot> $implicit The slots found by implicit column names
     */
    public function scope(Derivation $derivation, Relation $relation, QualifiedName $name, RowShape $shape, array $implicit): ConstraintScope
    {
        $names = [];
        foreach ($shape->slots as $slot) {
            if ($slot->name !== null) {
                $names[] = $slot->name;
            }
        }

        return new ConstraintScope(
            new Environment($derivation->context, null, [new VisibleRelation($relation, $shape, null, $name, [], $implicit)]),
            new Environment($derivation->context, null, [new VisibleRelation($relation, $shape, null, $name)]),
            $derivation->environment(),
            $shape->complete() ? $names : null,
            $derivation->context->columnNames,
        );
    }
}
