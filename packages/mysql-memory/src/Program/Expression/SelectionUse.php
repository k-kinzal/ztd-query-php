<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
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
    public function raise(DeferredSelection $selection, Session $session, bool $instruction = true, bool $viewDefinition = false): never
    {
        if ($instruction) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_select');
            $session->diagnostics->clear();
        }
        (new \MySqlMemory\Session\Problem\Identifiers())->check($selection, $session->settings()->release());
        \MySqlMemory\Session\Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $selection->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        $this->diagnosed($derivation->facts(), $session, $selection);
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session), viewDefinition: $viewDefinition);
        $planner = new \MySqlMemory\Plan\Planner($selection, $derivation->facts(), $session->settings(), $connection, $session->instance->dictionary);
        (new \MySqlMemory\Evaluation\Compile\Selections())->bound($selection, $planner);
    }

    /**
     * Reports grammar conditions before relation binding and operand preparation.
     *
     * @throws \MySqlMemory\Error\SqlError When a retained diagnostic precedes preparation
     */
    public function diagnosed(\SqlSemantics\Statement\Fact\Facts $facts, Session $session, ?DeferredSelection $selection = null): void
    {
        foreach ($facts->diagnostics as $problem) {
            if (Stages::parsed($problem)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        if ($selection !== null) {
            (new \MySqlMemory\Session\Access\DefaultDatabase())->calls($selection, $session);
        }
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof MissingTable || $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownQualifier) {
                throw (new Errors())->error($problem, $session);
            }
        }
    }

}
