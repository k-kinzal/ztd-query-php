<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Resolution\QueryPreparation;
use MySqlMemory\Session\Resolution\Views;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\State\StatementCounters;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery;

/**
 * Prepares an entire retained read query using the shared diagnostic ordering.
 *
 * @visibility MySqlMemory
 */
final class QueryUse
{
    /**
     * Prepares the query's already resolved dependencies without sibling conditions.
     */
    public function prepare(\SqlSemantics\Statement\Query $query, \MySqlMemory\Evaluation\Compile\Compiler $compiler, bool $exists = false, bool $deferSetWidth = false): void
    {
        $session = $compiler->connection->session();
        \SqlSemantics\Diagnostic\Check::invariant($session !== null, 'A deferred query prepares in its invocation session.');
        (new Problems())->raise(new QueryPreparation($query, $compiler->facts->forQuery($query), $session->semantics()->profile(), $exists, $deferSetWidth), $session);
    }

    /**
     * Raises the query's first dependency or intrinsic read-column condition.
     *
     * @throws \MySqlMemory\Error\SqlError Always, after preparing the retained query
     */
    public function raise(DeferredQuery $query, Session $session, bool $invocation = true): never
    {
        if ($invocation) {
            StatementCounters::query($session);
            StatementCounters::command($session, 'Com_select');
            $session->diagnostics->clear();
        }
        Views::refreshAll($session->instance->dictionary, $session->settings());
        $database = $session->variables->database;
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = new Derivation($context);
        $query->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        (new Problems())->raise(new QueryPreparation($query->query, $derivation->facts(), $context->profile), $session);
        throw (new Errors())->error($query->problem(), $session);
    }
}
