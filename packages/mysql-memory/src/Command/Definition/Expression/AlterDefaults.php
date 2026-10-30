<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Program\Expression\RowUse;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDefaultSetting;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use WeakReference;

/**
 * Prepares retained ALTER defaults after table lookup and all layout actions.
 * No rejected operand is replaced with a scalar or reparsed from source text.
 *
 * @visibility MySqlMemory
 */
final class AlterDefaults
{
    /**
     * Refuses stored calls in added column defaults before opening the ALTER target.
     * ALTER COLUMN SET DEFAULT keeps its later operand-resolution boundary.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand> $commands The ordered alterations
     */
    public function functions(array $commands, \SqlSemantics\Contract\GrammarRelease $release): void
    {
        $walker = new \MySqlMemory\Evaluation\Compile\Walker();
        foreach ($commands as $command) {
            foreach ($walker->find($command, \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition::class) as $column) {
                foreach ($column->specification->columnAttributes() as $attribute) {
                    if (!$attribute instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression) {
                        continue;
                    }
                    foreach ($this->calls($attribute->expression) as $call) {
                        if (!$call->native($release)) {
                            $name = ($call->schema === null ? '' : '`' . str_replace('`', '``', $call->schema->value) . '`.') . '`' . str_replace('`', '``', $call->name->value) . '`';
                            throw ConstraintError::DefaultFunction->error($column->name->column->value, $name);
                        }
                    }
                }
            }
        }
    }

    /**
     * Visits function arguments in written order before their enclosing call.
     *
     * @return list<\SqlSemantics\Platform\MySql\Statement\Call\FunctionCall>
     */
    public function calls(\SqlSemantics\Statement\Node $expression): array
    {
        $calls = [];
        foreach ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\Operands())->children($expression) as $child) {
            array_push($calls, ...$this->calls($child));
        }
        if ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall) {
            $calls[] = $expression;
        }
        return $calls;
    }

    /**
     * Derives the complete instruction against the current dictionary.
     */
    public function bound(AlterTable $definition, Session $session, string $database): AlterTable
    {
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, new SearchPath($database), $session->resolution());
        $bound = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $definition);
        \SqlSemantics\Diagnostic\Check::invariant($bound instanceof AlterTable, 'ALTER construction preserves its statement type.');
        return $bound;
    }

    /**
     * Resolves referenced tables before refusing the default's expression role.
     *
     * @throws \MySqlMemory\Error\SqlError When a retained default reaches its condition
     */
    public function check(AlterTable $definition, StoredTable $table, Session $session, string $database): void
    {
        $commands = array_values(array_filter($definition->commands, static fn ($command): bool => $command instanceof DeferredDefaultSetting));
        if ($commands === []) {
            return;
        }
        $definition = $this->bound($definition, $session, $database);
        $commands = array_values(array_filter($definition->commands, static fn ($command) => $command instanceof DeferredDefaultSetting));
        $facts = $definition->statementInformation();
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof MissingTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $evaluation = new Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new Connection($session->variables, $evaluation, program: $session->program, session: WeakReference::create($session));
        $planner = new Planner($commands[0]->expression, $facts, $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
        $scope = new Scope();
        $scope->clause = 'default value expression';
        $scope->place($definition, array_column($table->definition->columns, 'domain'), array_column($table->definition->columns, 'name'), $table->definition);
        foreach ($commands as $command) {
            $operand = $command->expression;
            $name = $command->column->column->value;
            if ($operand instanceof RowOperand && $planner->compiler->rows->subquery($operand) === null) {
                throw ConstraintError::DefaultRow->error($name);
            }
            (new ExpressionRules())->forbidden($operand, ExpressionRole::Default, $name);
            (new RowUse())->prepare($operand, $planner->compiler, $scope);
            throw (new RowUse())->error($operand);
        }
    }
}
