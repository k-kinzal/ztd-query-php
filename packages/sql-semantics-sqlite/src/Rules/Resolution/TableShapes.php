<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Resolution;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;

/**
 * Derives the row shape of one use of a named relation.
 *
 * Rule: SQLITE-TABLE-SHAPE-001. An unqualified name denotes the nearest
 * common table of that name, then a declared relation found along the
 * schema search path. A declared relation contributes one slot per declared
 * column, in order, each referring to the declaration, and is open when its
 * column list is incomplete; a common table contributes the slots it was
 * bound with; an undeclared or conditionally resolved name contributes an
 * open shape that names the missing declaration; a missing or conflicting
 * name contributes an empty shape and is a diagnostic. The implicit columns
 * of a declared table (rowid and its aliases) are reachable by name only.
 * Source: https://sqlite.org/lang_naming.html, https://sqlite.org/lang_with.html,
 * https://sqlite.org/rowidtable.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TableShapes
{
    /**
     * Resolves a relation name at a position and derives the shape of the use.
     */
    public function fact(Derivation $derivation, QualifiedName $name, Environment $environment): RelationFact
    {
        $resolution = $derivation->table($name, $environment);
        if ($resolution instanceof DeclaredTable) {
            $slots = [];
            foreach ($resolution->table->columns as $column) {
                $slots[] = new OutputSlot($column->name, new Known($column->type), $column->nullability, $column);
            }

            return new RelationFact(new RowShape($slots, $resolution->table->complete ? [] : [new IncompleteMembers($resolution->table)]), $resolution);
        }
        if ($resolution instanceof CommonTable) {
            $slots = [];
            foreach ($environment->commonTable($name->name)?->shape->slots ?? [] as $slot) {
                $slots[] = new OutputSlot($slot->name, $slot->type, $slot->nullability, null, $slot, $slot->unnamed);
            }

            return new RelationFact(new RowShape($slots, $environment->commonTable($name->name)?->shape->missing ?? []), $resolution);
        }
        if ($resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable) {
            return new RelationFact(new RowShape([], [$resolution->missing]), $resolution);
        }

        return new RelationFact(new RowShape([]), $resolution);
    }

    /**
     * Answers the implicit columns of the relation a use resolved to.
     *
     * @return list<ImplicitSlot>
     */
    public function implicit(RelationFact $fact): array
    {
        $implicit = [];
        foreach ($fact->table instanceof DeclaredTable ? $fact->table->table->implicit : [] as $column) {
            $implicit[] = new ImplicitSlot($column->names, new OutputSlot($column->column->name, new Known($column->column->type), $column->column->nullability, $column->column));
        }

        return $implicit;
    }
}
