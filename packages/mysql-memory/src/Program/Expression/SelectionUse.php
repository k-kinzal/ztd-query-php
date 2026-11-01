<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Stages;
use MySqlMemory\Session\Session;
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
     * @param bool $readingNotices Whether the caller has yet to emit expression input notices
     * @throws \MySqlMemory\Error\SqlError Always, after binding the reached operands
     */
    public function raise(DeferredSelection $selection, Session $session, bool $instruction = true, bool $viewDefinition = false, bool $readingNotices = true): never
    {
        $selection = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($selection);
        if ($instruction) {
            \MySqlMemory\Session\State\StatementCounters::query($session);
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_select');
            $session->diagnostics->clear();
        }
        (new \MySqlMemory\Session\Problem\Identifiers())->check($selection, $session->settings()->release());
        $database = $session->variables->database;
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = (new \MySqlMemory\Session\Resolution\Parameters())->derivation($selection, $context, ($session->preparation->resolution->parameters ?? []) + array_map(static fn (\MySqlMemory\Typing\Domain $domain): \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain => $domain->resolved(), $session->preparation->domains));
        $selection->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        if ($instruction) {
            (new \MySqlMemory\Program\Routine\SelectionNotices())->start($selection, (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation), $session);
        }
        $this->diagnosed((new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation), $session, $selection, viewDefinition: $viewDefinition, readingNotices: $readingNotices);
        if ($instruction) {
            (new \MySqlMemory\Program\Routine\SelectionNotices())->failed($selection, $session);
        }
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session), viewDefinition: $viewDefinition);
        $planner = new \MySqlMemory\Plan\Planner($selection, (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
        (new \MySqlMemory\Evaluation\Compile\Selections())->bound($selection, $planner);
    }

    /**
     * Reports grammar conditions before relation binding and operand preparation.
     *
     * @param bool $qualifiers Whether projection qualifiers are reached before a surrounding command opens its target
     * @param bool $viewDefinition Whether repeated relation aliases belong to a stored view definition
     * @param bool $readingNotices Whether expression input notices still need emission
     * @throws \MySqlMemory\Error\SqlError When a retained diagnostic precedes preparation
     */
    public function diagnosed(\SqlSemantics\Platform\MySql\Statement\StatementSemantics $facts, Session $session, ?DeferredSelection $selection = null, bool $qualifiers = true, bool $viewDefinition = false, bool $readingNotices = true): void
    {
        if ($selection !== null) {
            (new \MySqlMemory\Session\Access\RelationEntry())->check($selection, $facts, $session);
        }
        foreach ($facts->diagnostics as $problem) {
            if (Stages::parsed($problem)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        if ($selection !== null) {
            (new \MySqlMemory\Session\Problem\Reading())->into($selection, $session);
        }
        foreach ($facts->diagnostics as $problem) {
            if (!$viewDefinition && $problem instanceof \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable) {
                throw (new Errors())->error($problem, $session);
            }
        }
        if ($selection !== null) {
            (new \MySqlMemory\Session\Access\DefaultDatabase())->calls($selection, $session);
        }
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof MissingTable || ($qualifiers && $selection?->qualify === null && $problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownQualifier)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        foreach ($readingNotices ? $facts->warnings : [] as $warning) {
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation && $warning->construct === \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::AssignmentInExpression) {
                $session->diagnostics->warning($warning->code(), $warning->message());
            }
        }
    }

}
