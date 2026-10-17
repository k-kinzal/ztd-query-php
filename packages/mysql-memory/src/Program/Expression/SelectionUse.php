<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Scalar;
use WeakReference;

/**
 * Prepares a stored selection from its semantic relations and operands.
 *
 * An ordinary SQL instruction starts a new diagnostics area on every release.
 * Its input is opened before projections bind; no row-valued projection is
 * converted into a substitute scalar or a reconstructed SQL query.
 *
 * @visibility MySqlMemory
 */
final class SelectionUse
{
    /**
     * Raises the first binding condition or the retained scalar-use width error.
     *
     * @throws \MySqlMemory\Error\SqlError Always, after binding the reached operands
     */
    public function raise(DeferredSelection $selection, Session $session, bool $instruction = true): never
    {
        if ($instruction) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_select');
            $session->diagnostics->clear();
        }
        \MySqlMemory\Session\Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $selection->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        foreach ($derivation->facts()->diagnostics as $problem) {
            if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        foreach ($derivation->facts()->diagnostics as $problem) {
            if ($problem instanceof MissingTable || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownQualifier || ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse && $problem->rule === \SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::StarWithoutTables)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session));
        $planner = new \MySqlMemory\Plan\Planner($selection, $derivation->facts(), $session->settings(), $connection, $session->instance->dictionary);
        $scope = new \MySqlMemory\Evaluation\Scope();
        $width = $selection->from === null ? 0 : $planner->relations->plan($selection->from, $scope)->width();
        $scope->grouping = new \MySqlMemory\Plan\Grouping\Collected($planner, $width);
        foreach ($selection->items as $item) {
            if ($item instanceof DeferredProjection) {
                (new RowUse())->prepare($item->expression, $planner->compiler, $scope);
                throw QueryError::OperandColumns->error($item->expression instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand ? $item->expression->problem()->expected : 1);
            }
            if ($item instanceof SelectExpression) {
                $planner->compiler->compile($item->expression, $scope);
            }
        }
        $scope->grouping = null;
        if ($selection->where !== null && !$selection->where instanceof Scalar) {
            (new RowUse())->prepare($selection->where, $planner->compiler, $scope->inClause('where clause'));
        }
        throw QueryError::OperandColumns->error(1);
    }
}
