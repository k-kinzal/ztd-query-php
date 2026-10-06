<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\KindRule;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerTiming;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Checks the kind of the relation a command names against the kinds the command accepts.
 *
 * Rule: PG-RELATION-KIND-001. A relation declaration states whether it is a
 * base table (a partitioned table included), a view, a materialized view, a
 * foreign table or a sequence. A command that names a relation of a kind it
 * does not accept is the server's error, reported in its words: DROP, COMMENT
 * and ALTER EXTENSION written with one kind accept only that kind (DROP TABLE
 * of a view: `"v" is not a table`; nothing the context declares is an
 * index); ALTER, RENAME and SET SCHEMA written with VIEW, MATERIALIZED VIEW,
 * FOREIGN TABLE, SEQUENCE or INDEX accept only that kind, while ALTER TABLE
 * accepts every kind and ALTER INDEX ... RENAME any relation
 * (RangeVarCallbackForAlterRelation); a column or constraint of a sequence
 * cannot be renamed (`cannot rename columns of relation`). The commands with their own lists of
 * accepted kinds apply them where they resolve the relation. A relation the
 * context does not declare, or declares without stating a kind it can know,
 * is never reported.
 * Source: https://www.postgresql.org/docs/17/sql-droptable.html,
 * https://www.postgresql.org/docs/17/sql-alterview.html, `tablecmds.c` and
 * `objectaddress.c` (get_relation_by_qualified_name). Termination: one lookup
 * per relation. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RelationKinds
{
    /**
     * The kinds of relation whose columns and constraints can be renamed (renameatt_check).
     */
    public const COLUMNED = [RelationKind::BaseTable, RelationKind::View, RelationKind::MaterializedView, RelationKind::ForeignTable];

    /**
     * Answers the kind the declaration a relation fact resolved to states, or null when it resolved to none.
     */
    public function of(RelationFact $fact): ?RelationKind
    {
        return $fact->table instanceof DeclaredTable ? $fact->table->table->kind : null;
    }

    /**
     * Answers the kind of the relation a name resolves to, or null when it resolves to no declaration.
     */
    public function declared(Derivation $derivation, QualifiedName $name, ?Environment $environment = null): ?RelationKind
    {
        $resolution = $derivation->table($name, $environment ?? $derivation->environment());

        return $resolution instanceof DeclaredTable ? $resolution->table->kind : null;
    }

    /**
     * Reports the rule when the declared kind is known and is not one of the accepted kinds; tells whether it reported.
     *
     * @param list<RelationKind> $accepted
     */
    public function require(Derivation $derivation, ?RelationKind $kind, Name $relation, array $accepted, KindRule $rule): bool
    {
        if ($kind === null || in_array($kind, $accepted, true)) {
            return false;
        }
        $derivation->report(new KindProblem($rule, $relation));

        return true;
    }

    /**
     * Checks the relations of a command written with one relation kind that accepts only that kind: DROP, COMMENT and ALTER EXTENSION.
     *
     * @param list<ObjectReference> $objects
     */
    public function named(Derivation $derivation, ObjectKind $kind, array $objects): void
    {
        $expected = $this->expected($kind);
        if ($expected === null) {
            return;
        }
        foreach ($objects as $object) {
            $name = $this->name($object);
            if ($name !== null) {
                $this->require($derivation, $this->declared($derivation, $name), $name->name, $expected[0] === null ? [] : [$expected[0]], $expected[1]);
            }
        }
    }

    /**
     * Checks the relation of an ALTER, RENAME or SET SCHEMA command written with a relation kind; tells whether it reported.
     */
    public function altered(Derivation $derivation, ObjectKind $kind, QualifiedName $name, bool $rename): bool
    {
        $expected = $kind === ObjectKind::Table || ($kind === ObjectKind::Index && $rename) ? null : $this->expected($kind);

        return $expected !== null && $this->require($derivation, $this->declared($derivation, $name), $name->name, $expected[0] === null ? [] : [$expected[0]], $expected[1]);
    }

    /**
     * Checks the relation of ALTER ... RENAME (`$rename`) or SET SCHEMA written with a relation kind; a renamed column or constraint needs a relation that has columns.
     */
    public function renamed(Derivation $derivation, ObjectKind $kind, ObjectReference $object, bool $rename, bool $member): void
    {
        $name = $this->expected($kind) === null ? null : $this->name($object);
        if ($name === null) {
            return;
        }
        if ($member) {
            $this->require($derivation, $this->declared($derivation, $name), $name->name, self::COLUMNED, KindRule::RenameColumns);
        } else {
            $this->altered($derivation, $kind, $name, $rename);
        }
    }

    /**
     * Reports the target of INSERT, UPDATE, DELETE or MERGE when its declared kind cannot be changed.
     *
     * A sequence or a materialized view cannot be changed (CheckValidResultRel);
     * whether a view or a foreign table can depends on its definition, its
     * triggers or its wrapper. MERGE accepts only a table and, from
     * PostgreSQL 17, a view (transformMergeStmt).
     */
    public function modified(Derivation $derivation, ?RelationKind $kind, Name $relation, bool $merge): void
    {
        if ($merge) {
            $view = $derivation->context->profile->grammar === GrammarRelease::PostgreSql166 ? [] : [RelationKind::View];
            $this->require($derivation, $kind, $relation, [RelationKind::BaseTable, ...$view], KindRule::Merge);
        } elseif ($kind === RelationKind::Sequence || $kind === RelationKind::MaterializedView) {
            $derivation->report(new KindProblem($kind === RelationKind::Sequence ? KindRule::ChangeSequence : KindRule::ChangeMaterializedView, $relation));
        }
    }

    /**
     * Reports the constraints a foreign table cannot have among table elements, column qualifiers included.
     *
     * A foreign table has no primary key, unique, exclusion or foreign key
     * constraint (transformTableConstraint, transformColumnDefinition).
     *
     * @param list<Clause> $elements
     */
    public function foreign(Derivation $derivation, array $elements): void
    {
        foreach ($elements as $element) {
            $members = $element instanceof ColumnDefinition || $element instanceof ColumnOptions ? $element->qualifiers : [$element];
            foreach ($members as $member) {
                $rule = $member instanceof Constraint ? match ($member->kind()) {
                    ConstraintKind::PrimaryKey => KindRule::ForeignPrimaryKey,
                    ConstraintKind::Unique => KindRule::ForeignUnique,
                    ConstraintKind::Exclusion => KindRule::ForeignExclusion,
                    ConstraintKind::ForeignKey => KindRule::ForeignForeignKey,
                    ConstraintKind::Null, ConstraintKind::NotNull, ConstraintKind::Default, ConstraintKind::Identity, ConstraintKind::Generated, ConstraintKind::Check => null,
                } : null;
                if ($rule !== null) {
                    $derivation->report(new KindProblem($rule, null));
                }
            }
        }
    }

    /**
     * Reports a trigger the kind of its relation cannot have (CreateTriggerFiringOn).
     *
     * A table or a foreign table has no INSTEAD OF trigger, a view no row
     * trigger other than INSTEAD OF and no TRUNCATE trigger, a foreign table no
     * constraint trigger, neither a view nor a foreign table a trigger with
     * transition tables; a materialized view and a sequence have no trigger.
     */
    public function triggered(Derivation $derivation, ?RelationKind $kind, Name $relation, TriggerTiming $timing, bool $row, bool $truncate, bool $constraint, bool $transitions = false): void
    {
        $rule = match ($kind) {
            RelationKind::BaseTable => $timing === TriggerTiming::InsteadOf ? KindRule::TriggerOnTable : null,
            RelationKind::View => ($timing !== TriggerTiming::InsteadOf && $row) || $truncate || $transitions ? KindRule::TriggerOnView : null,
            RelationKind::ForeignTable => $timing === TriggerTiming::InsteadOf || $constraint || $transitions ? KindRule::TriggerOnForeignTable : null,
            RelationKind::MaterializedView, RelationKind::Sequence => KindRule::TriggersOnRelation,
            null => null,
        };
        if ($rule !== null) {
            $derivation->report(new KindProblem($rule, $relation));
        }
    }

    /**
     * Answers the kind a relation kind keyword accepts and the rule a relation of another kind breaks; null for the kinds that name no relation.
     *
     * @return array{RelationKind|null, KindRule}|null
     */
    public function expected(ObjectKind $kind): ?array
    {
        return match ($kind) {
            ObjectKind::Table => [RelationKind::BaseTable, KindRule::NotTable],
            ObjectKind::View => [RelationKind::View, KindRule::NotView],
            ObjectKind::MaterializedView => [RelationKind::MaterializedView, KindRule::NotMaterializedView],
            ObjectKind::ForeignTable => [RelationKind::ForeignTable, KindRule::NotForeignTable],
            ObjectKind::Sequence => [RelationKind::Sequence, KindRule::NotSequence],
            ObjectKind::Index => [null, KindRule::NotIndex],
            default => null,
        };
    }

    /**
     * Answers the relation name an object reference writes, or null when it writes none.
     */
    public function name(ObjectReference $object): ?QualifiedName
    {
        return match (true) {
            $object instanceof RelationTarget => $object->relation->name,
            $object instanceof DottedName => $object->qualified(),
            default => null,
        };
    }
}
