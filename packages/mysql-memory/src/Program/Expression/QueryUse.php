<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Resolution\QueryPreparation;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\State\StatementCounters;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery;
use WeakReference;

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
    public function prepare(\SqlSemantics\Statement\Query $query, \MySqlMemory\Evaluation\Compile\Compiler $compiler, bool $exists = false, bool $deferSetWidth = false, ?\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery $write = null): void
    {
        $session = $compiler->connection->session();
        \SqlSemantics\Diagnostic\Check::invariant($session !== null, 'A deferred query prepares in its invocation session.');
        (new Problems())->raise(new QueryPreparation($query, $compiler->facts->forQuery($query), $session->semantics()->profile(), $exists, $deferSetWidth, $write), $session);
    }

    /**
     * Raises the query's first dependency or intrinsic read-column condition.
     *
     * @throws \MySqlMemory\Error\SqlError Always, after preparing the retained query
     */
    public function raise(DeferredQuery $query, Session $session, bool $invocation = true): never
    {
        $query = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($query);
        if ($invocation) {
            StatementCounters::query($session);
            StatementCounters::command($session, 'Com_select');
            $session->diagnostics->clear();
        }
        $database = $session->variables->database;
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $derivation = (new \MySqlMemory\Session\Resolution\Parameters())->derivation($query, $context, ($session->preparation->resolution->parameters ?? []) + array_map(static fn (\MySqlMemory\Typing\Domain $domain): \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain => $domain->resolved(), $session->preparation->domains));
        $query->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        $preparation = new QueryPreparation($query->query, (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation), $context->profile);
        (new Problems())->raise($preparation, $session);
        if ($query->problem() instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint || $query->problem() instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement || $query->problem() instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\FrameConstraint || $query->problem() instanceof \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowOperandConstraint) {
            $this->plan($preparation, $session);
        }
        throw (new Errors())->error($query->problem(), $session);
    }
    /**
     * Prepares reached CTE templates through the same bound query and lexical scopes.
     *
     * Relation opening has already run. Scalar dependencies are prepared in query
     * order, so an inner row condition precedes an enclosing unknown projection.
     *
     * @throws \MySqlMemory\Error\SqlError When the reached template cannot prepare
     */
    public function templates(QueryPreparation $query, Session $session): void
    {
        $reached = (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->nodes($query->statement);
        if (array_filter($reached, static fn ($node): bool => $node instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody || $node instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredValuesQuery || $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem) === []) {
            return;
        }
        foreach ($query->facts->diagnostics as $problem) {
            if ($problem instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse && in_array($problem->rule, [\SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::RecursiveWithoutUnion, \SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::RecursiveWithoutAnchor], true)) {
                throw (new Errors())->error($problem, $session);
            }
        }
        $this->plan($query, $session);
    }

    /**
     * Compiles a retained query after the caller has checked its preceding conditions.
     *
     * @throws \MySqlMemory\Error\SqlError When query preparation is refused
     */
    public function plan(QueryPreparation $query, Session $session): void
    {
        $evaluation = new \MySqlMemory\Evaluation\Context($session->modes(), $session->diagnostics, $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $evaluation, $session->user, $session->host, $session->id, program: $session->program, session: WeakReference::create($session));
        $planner = new \MySqlMemory\Plan\Planner($query->statement, $query->facts, $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
        $planner->query($query->statement, null);
    }

}
