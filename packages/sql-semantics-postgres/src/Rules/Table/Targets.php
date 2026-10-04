<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
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
 * Resolves the relation a definition or command names, and builds the scope its expressions see.
 *
 * Rule: PG-TABLE-TARGET-001. The name resolves by CORE-TABLE-LOOKUP-001
 * along the search path (the temporary schema and `pg_catalog` first). A
 * declared relation contributes one slot per declared column, in order, each
 * referring to the declaration, and an open shape when its column list is
 * incomplete; an undeclared or conditionally resolved name contributes an
 * open shape that names the missing declaration; a missing or conflicting
 * name contributes an empty shape and is a diagnostic. The implicit columns
 * of a declaration (the system columns) are found by name only. The scope of
 * a definition's expressions has that relation as its only visible relation,
 * named by the relation name.
 * Source: https://www.postgresql.org/docs/17/ddl-schemas.html#DDL-SCHEMAS-PATH,
 * https://www.postgresql.org/docs/17/ddl-system-columns.html. Termination: one
 * pass over the columns. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Targets
{
    /**
     * Resolves the name of an existing relation and answers its facts.
     */
    public function resolve(Derivation $derivation, QualifiedName $name): RelationFact
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
     * Answers the slots found by the implicit column names of the declaration a fact resolved to.
     *
     * @return list<ImplicitSlot>
     */
    public function implicit(RelationFact $fact): array
    {
        $slots = [];
        foreach ($fact->table instanceof DeclaredTable ? $fact->table->table->implicit : [] as $implicit) {
            $column = $implicit->column;
            $slots[] = new ImplicitSlot($implicit->names, new OutputSlot($column->name, new Known($column->type), $column->nullability, $column));
        }

        return $slots;
    }

    /**
     * Answers an environment whose only visible relation is the given one.
     *
     * @param Relation $relation The node that stands for the relation
     * @param QualifiedName $name The name that qualifies its columns
     * @param RowShape $shape The row shape of the relation
     * @param list<ImplicitSlot> $implicit The slots found by implicit column names
     * @param Name|null $alias The correlation name that replaces the relation name, as OLD and NEW do
     */
    public function scope(Derivation $derivation, Relation $relation, QualifiedName $name, RowShape $shape, array $implicit = [], ?Name $alias = null): Environment
    {
        return new Environment($derivation->context, null, [new VisibleRelation($relation, $shape, $alias, $alias === null ? $name : null, [], $implicit)]);
    }
}
