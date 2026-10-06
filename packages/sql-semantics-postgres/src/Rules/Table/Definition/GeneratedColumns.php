<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\KeyColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\DefaultExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\Generated;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\Identity;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\References;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceAction;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceEvent;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferentialAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\PartitionOf;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\TableForm;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Validation\ValueGraph;

/**
 * Reports the definitions PostgreSQL rejects because a column is, or is not, a generated column.
 *
 * Rule: PG-GENERATED-COLUMN-001. A generation expression may not use a
 * generated column (`cannot use generated column "b" in column generation
 * expression`, check_nested_generated, heap.c); a partition key, by name or
 * in an expression, may not use one (`cannot use generated column in
 * partition key`, ComputePartitionAttrs, tablecmds.c); a foreign key whose
 * referencing columns include one may not have ON UPDATE SET NULL, SET
 * DEFAULT or CASCADE, nor ON DELETE SET NULL or SET DEFAULT (`invalid ON
 * UPDATE action for foreign key constraint containing generated column`,
 * ATAddForeignKeyConstraint). A table column inherited by several parents
 * must be generated in all or none (`inherited column "b" has a generation
 * conflict`); a column definition, or the options of a partition column,
 * merged into an inherited generated column may not have a default or an
 * identity, and one merged into an inherited column that is not generated
 * may not have a generation clause (`child column "b" specifies generation
 * expression`; MergeAttributes). A column counts as generated when the
 * expression resolves it to a declared generated column, or the declaration
 * of the table or parent says so; the first such column is reported, in
 * written order.
 * Source: https://www.postgresql.org/docs/17/ddl-generated-columns.html,
 * https://www.postgresql.org/docs/16/ddl-generated-columns.html,
 * https://github.com/postgres/postgres/blob/REL_17_2/src/backend/commands/tablecmds.c,
 * https://github.com/postgres/postgres/blob/REL_17_2/src/backend/catalog/heap.c.
 * Termination: one pass over the expression, the parents and the elements. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class GeneratedColumns
{
    /**
     * Answers the first generated column, in written order, that an expression derived so far resolves to, or null when it uses none.
     */
    public function referenced(Derivation $derivation, Scalar $expression): ?Name
    {
        $facts = $derivation->facts();
        foreach (array_reverse((new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Platform\\PostgreSql\\Statement\\']))->objects($expression)) as $object) {
            $resolution = $object instanceof Scalar && $facts->covers($object) ? $facts->scalar($object)->resolution : null;
            $column = $resolution instanceof ResolvedColumn ? $resolution->declaration() : null;
            if ($column !== null && $column->generated) {
                return $column->name;
            }
        }

        return null;
    }

    /**
     * Reports a generation expression, derived before, that uses a generated column.
     */
    public function expression(Derivation $derivation, Scalar $expression): void
    {
        $column = $this->referenced($derivation, $expression);
        if ($column !== null) {
            $derivation->report(new DefinitionProblem(DefinitionRule::GeneratedInGeneration, $column));
        }
    }

    /**
     * Reports a partition key, derived before, that uses a generated column.
     */
    public function partitionKey(Derivation $derivation, Scalar $key): void
    {
        if ($this->referenced($derivation, $key) !== null) {
            $derivation->report(new DefinitionProblem(DefinitionRule::GeneratedPartitionKey));
        }
    }

    /**
     * Tells whether the relation of an environment has a generated column among the named columns.
     *
     * @param list<Name> $columns
     */
    public function among(Derivation $derivation, Environment $environment, array $columns): bool
    {
        $keys = new KeyColumns();
        foreach ($environment->relations as $relation) {
            foreach ($columns as $column) {
                $position = $keys->position($derivation, $relation->shape, $column);
                if ($position !== null && $relation->shape->slots[$position]->declaration()?->generated === true) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Reports a referential action that a foreign key containing a generated column cannot have.
     *
     * @param list<ReferentialAction> $actions
     */
    public function keyActions(Derivation $derivation, array $actions): void
    {
        $forbidden = [
            ReferenceEvent::Update->value => [ReferenceAction::SetNull, ReferenceAction::SetDefault, ReferenceAction::Cascade],
            ReferenceEvent::Delete->value => [ReferenceAction::SetNull, ReferenceAction::SetDefault],
        ];
        foreach ([ReferenceEvent::Update, ReferenceEvent::Delete] as $event) {
            foreach ($actions as $action) {
                if ($action->event === $event && in_array($action->action, $forbidden[$event->value], true)) {
                    $derivation->report(new DefinitionProblem(DefinitionRule::GeneratedKeyAction, new Name('ON ' . $event->value)));

                    return;
                }
            }
        }
    }

    /**
     * Reports the referential actions of the REFERENCES constraints of a generated column definition.
     *
     * @param list<Clause> $qualifiers The qualifiers of the column definition
     */
    public function columnKeys(Derivation $derivation, array $qualifiers): void
    {
        if (array_filter($qualifiers, static fn (Clause $qualifier): bool => $qualifier instanceof Generated) === []) {
            return;
        }
        foreach ($qualifiers as $qualifier) {
            if ($qualifier instanceof References) {
                $this->keyActions($derivation, $qualifier->actions);
            }
        }
    }

    /**
     * Reports the columns of a table definition whose generation does not fit the columns they inherit.
     */
    public function inherited(Derivation $derivation, TableForm $form): void
    {
        $parents = match (true) {
            $form instanceof ListedColumns => array_map(static fn ($parent) => $parent->name, $form->parents),
            $form instanceof PartitionOf => [$form->parent->name],
            default => [],
        };
        $inherited = [];
        foreach ($parents as $parent) {
            $resolution = $derivation->table($parent, $derivation->environment());
            foreach ($resolution instanceof DeclaredTable ? $resolution->table->columns : [] as $column) {
                $key = $derivation->context->columnNames->fold($column->name->value);
                if (isset($inherited[$key]) && $inherited[$key] !== $column->generated) {
                    $derivation->report(new DefinitionProblem(DefinitionRule::InheritedGenerationConflict, $column->name));
                }
                $inherited[$key] ??= $column->generated;
            }
        }
        foreach ($form->elements() as $element) {
            if (($element instanceof ColumnDefinition || $element instanceof ColumnOptions) && isset($inherited[$derivation->context->columnNames->fold($element->name->value)])) {
                $this->merged($derivation, $element->name, $element->qualifiers, $inherited[$derivation->context->columnNames->fold($element->name->value)]);
            }
        }
    }

    /**
     * Reports a column definition merged into an inherited column whose generation it does not fit.
     *
     * @param list<Clause> $qualifiers
     * @param bool $generated Whether the inherited column is generated
     */
    public function merged(Derivation $derivation, Name $column, array $qualifiers, bool $generated): void
    {
        $kinds = array_map(static fn (Clause $qualifier): string => $qualifier::class, $qualifiers);
        $rule = match (true) {
            $generated && in_array(DefaultExpression::class, $kinds, true) && !in_array(Generated::class, $kinds, true) => DefinitionRule::GeneratedInheritsDefault,
            $generated && in_array(Identity::class, $kinds, true) => DefinitionRule::GeneratedInheritsIdentity,
            !$generated && in_array(Generated::class, $kinds, true) => DefinitionRule::ChildGeneration,
            default => null,
        };
        if ($rule !== null) {
            $derivation->report(new DefinitionProblem($rule, $column));
        }
    }
}
