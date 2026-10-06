<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnTyping;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Declarations;
use SqlSemantics\Platform\PostgreSql\Rules\Table\KeyColumns;
use SqlSemantics\Platform\PostgreSql\Rules\Table\RelationKinds;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\DeclaredTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\IndexConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table\TablePrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AddConstraint;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\AddColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\AddIdentity;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnActionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\AttachIndex;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\AttachPartition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachPartition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives ALTER TABLE and the other commands built on `AlterTableStmt`.
 *
 * Rule: PG-ALTER-TABLE-001. The altered relation is resolved
 * (PG-TABLE-TARGET-001) and is the relation fact of the statement; the
 * statement changes no declaration ("ALTER ... does not change the
 * context"). Each action is derived where the relation is the only visible
 * relation, its system columns included; the expressions of an added column
 * also see that column. A column an action names must be a column of a
 * relation whose column list is complete, unless IF EXISTS is written. The
 * kind the relation is written with and the actions must fit its declared
 * kind (PG-RELATION-KIND-001, PG-ALTER-KIND-001). An identity can be added
 * only to a column that is NOT NULL, as declared or made by SET NOT NULL or
 * a primary key in the same statement ("must be declared NOT NULL before
 * identity can be added", ATExecAddIdentity). The column actions must fit
 * the generated columns of the relation (PG-ALTER-GENERATED-001).
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 * Termination: one pass over the actions. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Alterations
{
    /**
     * Resolves the relation and derives the actions.
     */
    public function derive(AlterTable $alter, Derivation $derivation): void
    {
        $targets = new Targets();
        $fact = $derivation->relation($alter, $derivation->environment());
        $scope = $targets->scope($derivation, $alter, $alter->relation->name, $fact->shape, $targets->implicit($fact));
        $kinds = new RelationKinds();
        if (!$kinds->altered($derivation, ObjectKind::from($alter->target->value), $alter->relation->name, false)) {
            (new AlterKinds())->check($derivation, $kinds->of($fact), $alter->relation->name->name, $alter->commands);
        }
        if ($kinds->of($fact) === RelationKind::ForeignTable) {
            (new AlterKinds())->foreign($derivation, $alter->commands);
        }
        foreach ($alter->commands as $command) {
            if ($command instanceof AddIdentity) {
                $this->identity($derivation, $fact->shape, $alter, $command->column);
            }
        }
        (new GeneratedChanges())->check($derivation, $fact->shape, $alter);
        foreach ($alter->commands as $command) {
            $command->deriveClause($derivation, $command instanceof AddColumn ? $this->widened($scope, $command, $derivation) : $scope);
        }
    }

    /**
     * Reports an identity added to a column the declaration lets be NULL.
     *
     * The column must be NOT NULL when the identity is added; a SET NOT NULL
     * or a primary key on the column in the same statement runs first.
     */
    public function identity(Derivation $derivation, RowShape $shape, AlterTable $alter, Name $column): void
    {
        $position = $shape->complete() ? (new KeyColumns())->position($derivation, $shape, $column) : null;
        if ($position === null || $shape->slots[$position]->nullability !== Nullability::Nullable) {
            return;
        }
        $comparison = $derivation->context->columnNames;
        foreach ($alter->commands as $command) {
            $constraint = $command instanceof AddConstraint ? $command->constraint : null;
            $keys = $constraint instanceof TablePrimaryKey ? $constraint->columns : [];
            if ($constraint instanceof IndexConstraint && $constraint->primary) {
                return;
            }
            if ($command instanceof ColumnAction && $command->kind === ColumnActionKind::SetNotNull) {
                $keys = [$command->column];
            }
            foreach ($keys as $key) {
                if ($comparison->equal($key->value, $column->value)) {
                    return;
                }
            }
        }
        $derivation->report(new DefinitionProblem(DefinitionRule::IdentityNullable, $column, $alter->relation->name->name));
    }

    /**
     * Answers the scope of an added column: the relation with that column after its columns.
     */
    public function widened(Environment $scope, AddColumn $command, Derivation $derivation): Environment
    {
        $relations = [];
        $definition = $command->column;
        $type = (new ColumnTyping())->descriptor($definition->type, $derivation->context);
        $slot = new OutputSlot(
            $definition->name,
            $type === null ? $definition->type->typeFact($derivation->context) : (new DeclaredTyping())->fact($type),
            (new Declarations())->notNull($definition->qualifiers) ? Nullability::NotNull : Nullability::Nullable,
        );
        foreach ($scope->relations as $relation) {
            $relations[] = new VisibleRelation($relation->relation, new RowShape([...$relation->shape->slots, $slot], $relation->shape->missing), $relation->alias, $relation->name, $relation->hidden, $relation->implicit);
        }

        return new Environment($scope->context, $scope->outer, $relations, $scope->commonTables, $scope->aliases);
    }

    /**
     * Reports a column the action names that the complete row shape of the relation lacks.
     */
    public function column(Derivation $derivation, Environment $environment, Name|IntegerConstant $column): void
    {
        if (!$column instanceof Name) {
            return;
        }
        foreach ($environment->relations as $relation) {
            if ($relation->shape->complete() && (new KeyColumns())->position($derivation, $relation->shape, $column) === null) {
                $derivation->report(new DefinitionProblem(DefinitionRule::MissingColumn, $column));
            }
        }
    }

    /**
     * Tells whether the actions of a command list may stand together: a partition action stands alone.
     *
     * @param list<AlterCommand> $commands
     */
    public function admits(array $commands): bool
    {
        if (count($commands) < 2) {
            return $commands !== [];
        }
        foreach ($commands as $command) {
            if ($command instanceof AttachPartition || $command instanceof DetachPartition || $command instanceof AttachIndex) {
                return false;
            }
        }

        return true;
    }
}
