<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ImproperName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\ObjectProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\MemberName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;

/**
 * Resolves the relations and columns a generic object command names.
 *
 * Rule: PG-OBJECT-RELATION-001. A table, view, materialized view or foreign
 * table named by DROP, COMMENT, SECURITY LABEL or an ALTER command, the table
 * a policy, rule, trigger or table constraint belongs to, and the table of a
 * column are resolved along the search path (CORE-TABLE-LOOKUP-001) and
 * recorded as the relation fact of the object reference: a declared relation
 * with its columns, an undeclared one as an open shape naming the missing
 * declaration, a missing or conflicting one as a diagnostic. A relation that
 * IF EXISTS lets the server skip is recorded without a table and is no
 * diagnostic. A dotted relation name of more than three parts is reported. A
 * column is named by a dotted name whose last part is the column: one part is
 * reported as unqualified, more than four parts as an improper relation name,
 * and a column the declared complete relation certainly lacks is reported.
 * Renaming a column to a name the relation certainly has is reported.
 * Indexes and sequences are not declarations the context can hold and are not
 * resolved. Terminates: one lookup per reference.
 * Source: https://www.postgresql.org/docs/17/sql-comment.html, https://www.postgresql.org/docs/17/sql-droptable.html,
 * https://www.postgresql.org/docs/17/sql-altertable.html, https://www.postgresql.org/docs/17/ddl-schemas.html#DDL-SCHEMAS-PATH.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RelationTargets
{
    /**
     * Resolves the relation an object reference names, if its kind names one, and records the fact; answers it.
     */
    public function resolve(ObjectKind $kind, ObjectReference $object, bool $ifExists, Derivation $derivation): ?RelationFact
    {
        $relation = in_array($kind->value, ObjectForms::RELATION, true);
        if ($kind === ObjectKind::Column && $object instanceof DottedName) {
            return $this->column($object, $derivation);
        }
        if ($relation && $object instanceof DottedName) {
            return $this->dotted($object, $object, $ifExists, $derivation);
        }
        if ($relation && $object instanceof RelationTarget) {
            return $this->record($object, $object->relation->name, $ifExists, $derivation);
        }
        if ($object instanceof MemberName && in_array($kind, [ObjectKind::Policy, ObjectKind::Rule, ObjectKind::Trigger, ObjectKind::Constraint], true)) {
            return $object->owner instanceof QualifiedName ? $this->record($object, $object->owner, $ifExists, $derivation) : $this->dotted($object, $object->owner, $ifExists, $derivation);
        }

        return null;
    }

    /**
     * Resolves a relation written as a dotted name and records the fact on the reference.
     */
    public function dotted(ObjectReference $reference, DottedName $name, bool $ifExists, Derivation $derivation): RelationFact
    {
        $qualified = $name->qualified();
        if ($qualified === null) {
            $derivation->report(new ImproperName($name));

            return $derivation->target($reference, new RelationFact(new RowShape([])));
        }

        return $this->record($reference, $qualified, $ifExists, $derivation);
    }

    /**
     * Resolves a relation name and records the fact on the reference.
     */
    public function record(ObjectReference $reference, QualifiedName $name, bool $ifExists, Derivation $derivation): RelationFact
    {
        $resolution = $derivation->table($name, $derivation->environment());
        if ($ifExists && $resolution instanceof MissingTable) {
            return $derivation->target($reference, new RelationFact(new RowShape([])));
        }
        $shape = new RowShape([]);
        if ($resolution instanceof DeclaredTable) {
            $slots = [];
            foreach ($resolution->table->columns as $column) {
                $slots[] = new OutputSlot($column->name, new Known($column->type), $column->nullability, $column);
            }
            $shape = new RowShape($slots, $resolution->table->complete ? [] : [new IncompleteMembers($resolution->table)]);
        } elseif ($resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable) {
            $shape = new RowShape([], [$resolution->missing]);
        }

        return $derivation->target($reference, new RelationFact($shape, $resolution));
    }

    /**
     * Resolves the table of a column written as a dotted name, records the fact on the name and reports a column the table certainly lacks.
     */
    public function column(DottedName $name, Derivation $derivation): RelationFact
    {
        $parts = $name->parts;
        $count = count($parts);
        if ($count < 2 || $count > 4) {
            $derivation->report($count < 2 ? new ObjectProblem(ObjectProblemKind::UnqualifiedColumn) : new ObjectProblem(ObjectProblemKind::ImproperRelation, $this->spelled($parts)));

            return $derivation->target($name, new RelationFact(new RowShape([])));
        }
        $table = (new DottedName(array_slice($parts, 0, $count - 1)))->qualified() ?? new QualifiedName($parts[0]);
        $fact = $this->record($name, $table, false, $derivation);
        $this->member($fact, $parts[$count - 1], $table, $derivation);

        return $fact;
    }

    /**
     * Reports a column that a declared complete relation certainly lacks.
     */
    public function member(RelationFact $fact, Name $column, QualifiedName $table, Derivation $derivation): void
    {
        if ($this->has($fact, $column, $derivation) === false) {
            $derivation->report(new MissingColumn($column, $table));
        }
    }

    /**
     * Reports renaming a column to a name a declared relation certainly has.
     */
    public function renamed(RelationFact $fact, Name $column, Derivation $derivation): void
    {
        if ($this->has($fact, $column, $derivation) === true) {
            $derivation->report(new ObjectProblem(ObjectProblemKind::ColumnExists, '"' . $column->value . '"'));
        }
    }

    /**
     * Tells whether the relation of a fact has a column of the name: true, false, or null when its declaration does not tell.
     */
    public function has(RelationFact $fact, Name $column, Derivation $derivation): ?bool
    {
        if (!$fact->table instanceof DeclaredTable) {
            return null;
        }
        if ($fact->table->table->matchingColumns($column->value, $derivation->context->columnNames) !== []) {
            return true;
        }

        return $fact->table->table->complete ? false : null;
    }

    /**
     * Spells dotted name parts for a message.
     *
     * @param list<Name> $parts
     */
    public function spelled(array $parts): string
    {
        $values = [];
        foreach ($parts as $part) {
            $values[] = $part->value;
        }

        return implode('.', $values);
    }
}
