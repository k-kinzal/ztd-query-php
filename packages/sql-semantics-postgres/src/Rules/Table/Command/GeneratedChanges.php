<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\KeyColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnAction;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnActionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\DefaultChange;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\SetExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\TypeChange;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * Reports the ALTER TABLE column actions that do not fit whether the column is a generated column.
 *
 * Rule: PG-ALTER-GENERATED-001. SET DEFAULT and DROP DEFAULT refuse a
 * generated column (`column "b" of relation "t" is a generated column`,
 * ATExecColumnDefault; the server hints at SET EXPRESSION or DROP
 * EXPRESSION), unless the same statement drops its generation expression,
 * which runs first. DROP EXPRESSION without IF EXISTS refuses a column that
 * is not a stored generated column (`column "a" of relation "t" is not a
 * stored generated column`, ATExecDropExpression); with IF EXISTS the server
 * only notices it. SET EXPRESSION, from PostgreSQL 17, refuses a column that
 * is not a generated column (ATExecSetExpression). SET DATA TYPE with USING
 * refuses a generated column (`cannot specify USING when altering type of
 * generated column`, ATPrepAlterColumnType). A column counts when the
 * declared column list of the relation is complete and holds it.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html,
 * https://www.postgresql.org/docs/16/sql-altertable.html,
 * https://github.com/postgres/postgres/blob/REL_17_2/src/backend/commands/tablecmds.c.
 * Termination: one pass over the commands. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class GeneratedChanges
{
    /**
     * Reports the column actions of the statement that do not fit the generated columns of the relation.
     */
    public function check(Derivation $derivation, RowShape $shape, AlterTable $alter): void
    {
        foreach ($alter->commands as $command) {
            if ($command instanceof TypeChange && $command->using !== null && $this->generated($derivation, $shape, $command->column) === true) {
                $derivation->report(new DefinitionProblem(DefinitionRule::GeneratedTypeUsing));
            }
            if (!$command instanceof DefaultChange && !$command instanceof ColumnAction && !$command instanceof SetExpression) {
                continue;
            }
            $rule = $this->rule($command, $this->generated($derivation, $shape, $command->column), $this->dropped($derivation, $alter, $command->column));
            if ($rule !== null) {
                $derivation->report(new DefinitionProblem($rule, $command->column, $alter->relation->name->name));
            }
        }
    }

    /**
     * Answers the rule a column action breaks, or null.
     *
     * @param bool|null $generated Whether the column is generated, or null when the relation does not tell
     * @param bool $dropped Whether the statement drops the generation expression of the column
     */
    public function rule(DefaultChange|ColumnAction|SetExpression $command, ?bool $generated, bool $dropped): ?DefinitionRule
    {
        $kind = $command instanceof ColumnAction ? $command->kind : null;
        if ($command instanceof DefaultChange || $kind === ColumnActionKind::DropDefault) {
            return $generated === true && !$dropped ? DefinitionRule::GeneratedColumnDefault : null;
        }
        if ($generated !== false) {
            return null;
        }
        if ($command instanceof SetExpression) {
            return DefinitionRule::NotGenerated;
        }

        return $kind === ColumnActionKind::DropExpression ? DefinitionRule::NotStoredGenerated : null;
    }

    /**
     * Tells whether the relation declares a column as generated, or null when the complete column list does not hold it.
     */
    public function generated(Derivation $derivation, RowShape $shape, Name $column): ?bool
    {
        $position = $shape->complete() ? (new KeyColumns())->position($derivation, $shape, $column) : null;

        return $position === null ? null : $shape->slots[$position]->declaration()?->generated === true;
    }

    /**
     * Tells whether the statement drops the generation expression of a column.
     */
    public function dropped(Derivation $derivation, AlterTable $alter, Name $column): bool
    {
        foreach ($alter->commands as $command) {
            $drops = $command instanceof ColumnAction && ($command->kind === ColumnActionKind::DropExpression || $command->kind === ColumnActionKind::DropExpressionIfExists);
            if ($drops && $derivation->context->columnNames->equal($command->column->value, $column->value)) {
                return true;
            }
        }

        return false;
    }
}
