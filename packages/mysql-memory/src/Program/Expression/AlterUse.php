<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Command\Definition\AlterTableCommand;
use MySqlMemory\Command\Definition\TableChange;
use MySqlMemory\Command\Definition\TableLayout;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;

/**
 * Opens a retained ALTER target and applies its ordered declaration changes.
 * The local layout remains unpublished when the generation condition is reached.
 *
 * @visibility MySqlMemory
 */
final class AlterUse
{
    /**
     * @param \MySqlMemory\Command\Definition\Expression\GenerationRewrite $rewrite The current execution's structural substitutions
     */
    public function __construct(public readonly \MySqlMemory\Command\Definition\Expression\GenerationRewrite $rewrite = new \MySqlMemory\Command\Definition\Expression\GenerationRewrite())
    {
    }

    /**
     * Resolves the target database without opening the table during preparation.
     *
     * @throws SqlError When the retained header or partition clause is invalid
     */
    public function header(AlterTable $definition, Session $session, ?string $database = null): string
    {
        $database = $definition->table->schema->value ?? $database ?? $session->variables->database;
        if ($database === '') {
            throw QueryError::NoDatabase->error();
        }
        (new \MySqlMemory\Command\Definition\Expression\GenerationExecution())->variables($definition, $session);
        $facts = (new \MySqlMemory\Command\Definition\Expression\AlterDefaults())->facts($definition, $session, $database);
        foreach ($facts->diagnostics as $problem) {
            if (\MySqlMemory\Session\Problem\Stages::parsed($problem) || \MySqlMemory\Session\Problem\Stages::characters($problem, $session->settings()->legacy())) {
                throw (new \MySqlMemory\Session\Problem\Errors())->error($problem, $session);
            }
        }
        (new AlterTableCommand())->read($definition->commands, $facts, $session->settings()->legacy());
        (new \MySqlMemory\Command\Definition\StorageOptions())->resolve($definition, $session, new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant()), $database);
        foreach ($facts->warnings as $warning) {
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $warning->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedScalar && $warning->problem->condition === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Subquery) {
                throw new SqlError(\MySqlMemory\Error\Family\StatementError::ParseError, $warning->problem->message(), new \SqlSemantics\Construction\RejectedInput($warning->problem, 0));
            }
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $warning->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\InvalidPartitionExpression) {
                throw new SqlError(\MySqlMemory\Error\Family\StatementError::ParseError, $warning->problem->message(), new \SqlSemantics\Construction\RejectedInput($warning->problem, 0));
            }
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure && $warning->problem instanceof \SqlSemantics\Platform\MySql\Statement\Partition\Problem\MissingPartitions) {
                throw (new \MySqlMemory\Session\Problem\Errors())->error($warning->problem, $session);
            }
        }
        return $database;
    }

    /**
     * Preserves table and column errors before the retained generation condition.
     *
     * @throws SqlError When target lookup or a declaration is refused
     */
    public function raise(AlterTable $definition, Session $session, ?string $database = null): \MySqlMemory\Result\Reply
    {
        $database = $this->header($definition, $session, $database);
        $session->transaction->commit();
        if ((new \SqlSemantics\Platform\MySql\Rules\TableChange\AlterationRoles())->transaction($definition->commands)) {
            throw \MySqlMemory\Error\Family\TransactionError::CreateTransactionInvalid->error('with ALTER TABLE command.');
        }
        (new AlterTableCommand())->validation($definition->commands);
        $name = $definition->table->name->value;
        $table = (new AlterTableCommand())->table($session, $database, $name);
        if ((new \SqlSemantics\Platform\MySql\Rules\Partition\MethodRoles())->commands($definition->commands)) {
            foreach ((new \MySqlMemory\Command\Definition\Expression\AlterDefaults())->facts($definition, $session, $database)->diagnostics as $problem) {
                if ($problem instanceof \SqlSemantics\Statement\Reference\Table\MissingTable) {
                    throw (new \MySqlMemory\Session\Problem\Errors())->error($problem, $session);
                }
            }
            throw \MySqlMemory\Error\Family\PartitionError::FunctionNotAllowed->error();
        }
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles())->commands($definition->commands)) {
            $prepared = new \MySqlMemory\Session\Resolution\IndexPreparation($definition, (new \MySqlMemory\Command\Definition\Expression\AlterDefaults())->facts($definition, $session, $database));
            (new \MySqlMemory\Session\Problem\Definition\FunctionalQueries())->prepare($prepared, $session, new \MySqlMemory\Session\Problems());
            (new AlterTableCommand())->resolve($prepared, $session);
        }
        $layout = TableLayout::of($table->definition);
        $change = new TableChange($layout, $name, $database, $session->settings()->release(), (string) $session->variables->read('collation_database'));
        $change->apply($definition->commands);
        if (isset($prepared)) {
            (new \MySqlMemory\Session\Problem\Definition\IndexNames())->alter($layout, $prepared->facts, $session->settings()->release(), $table->definition->engine, $table->definition->collation);
        }
        (new \MySqlMemory\Command\Definition\Expression\AlterDefaults())->check($definition, $table, $session, $database);
        $rewrite = $this->rewrite;
        $columns = array_map($rewrite->column(...), array_column($layout->columns, 0));
        if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->columns($columns)) {
            throw (new \MySqlMemory\Command\Definition\Expression\GenerationCondition())->columns($columns, $database, $name, true, (new \MySqlMemory\Command\Definition\Expression\AlterDefaults())->facts($definition, $session, $database), $session);
        }
        $statement = new AlterTable($definition->table, $rewrite->commands($definition), $definition->ignore);
        $execution = new \MySqlMemory\Command\Definition\Expression\GenerationExecution();
        $reply = $execution->run($statement, $session, $database);
        $execution->display($table, $definition, $session, $rewrite->parameters);
        return $reply;
    }
}
