<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\ColumnTyping;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\Declarations;
use SqlSemantics\Platform\PostgreSql\Rules\Table\KeyColumns;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\AddColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\AttachIndex;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\AttachPartition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachPartition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
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
 * relation whose column list is complete, unless IF EXISTS is written.
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
        foreach ($alter->commands as $command) {
            $command->deriveClause($derivation, $command instanceof AddColumn ? $this->widened($scope, $command, $derivation) : $scope);
        }
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
            $type === null ? $definition->type->typeFact($derivation->context) : new Known($type),
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
