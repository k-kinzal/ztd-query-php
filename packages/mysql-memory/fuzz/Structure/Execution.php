<?php

declare(strict_types=1);

namespace Fuzz\Structure;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Execution as StatementExecution;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Scalar;

/**
 * Executes a generated semantic structure in a fresh in-memory database.
 *
 * A general expression is placed in a SELECT item. No generated SQL is
 * fed to the memory session, and no diagnostic or SQL error is discarded.
 */
final class Execution
{
    /**
     * Derives the structure against the session context and executes it directly.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Values and domains bound to the generated parameter occurrences
     * @throws \MySqlMemory\Error\SqlError When the database rejects the generated structure
     */
    public function run(Statement|Scalar|\SqlSemantics\Statement\Query $node, string $release = '8.4.7', array $parameters = [], ?string $mode = null): Reply
    {
        $session = (new Instance($release))->connect();
        if ($mode !== null) {
            $session->variables->session['sql_mode'] = $mode;
        }

        return $this->runIn($session, $node, $parameters);
    }

    /**
     * Executes directly constructed structures against shared session state.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The values bound to parameter occurrences
     * @param bool $prepared Whether to prepare the structure even with no outer parameters
     * @throws \MySqlMemory\Error\SqlError When execution rejects the structure
     */
    public function runIn(Session $session, Statement|Scalar|\SqlSemantics\Statement\Query $node, array $parameters = [], bool $prepared = false): Reply
    {
        $prepared = $prepared || $parameters !== [];
        if ($session->running->using === []) {
            $session->activity->begin();
            \MySqlMemory\Session\State\StatementCounters::received($session, !$prepared);
        }
        $session->hinted = [];
        $session->dots = [];
        $statement = (new Embedding())->statement($node);
        $operation = $this->operation($session, $statement, $parameters);

        return $this->execute($session, $operation, $parameters, $prepared);
    }

    /**
     * Resolves an original statement in the session's current typing context.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and their domains
     */
    public function operation(Session $session, Statement $statement, array $parameters): Operation
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-' . $session->instance->version, Mode::fromString((string) $session->variables->read('sql_mode')), $this->parameters($statement));
        return (new \MySqlMemory\Session\Statement\Binding())->operation($session, $statement, $parameters, $semantics->profile());
    }

    /**
     * Uses the shared typed preparation path when bindings require the native
     * prepared protocol, then executes and closes that one-shot statement.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and domains
     * @throws \MySqlMemory\Error\SqlError When preparation or execution rejects the operation
     */
    public function execute(Session $session, Operation $operation, array $parameters, bool $prepared = false): Reply
    {
        $execution = new StatementExecution($session);
        $command = (new Dispatcher())->command($operation->statement);
        if ($parameters === [] && !$prepared) {
            return $execution->perform($operation, $command);
        }
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_prepare');
        $execution->area($operation, $command);
        (new \MySqlMemory\Session\Access\Preparation())->check($operation, $session);
        \MySqlMemory\Session\State\StatementCounters::received($session);
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_execute');
        $replayed = $session->replayed;
        $session->replayed = true;
        try {
            return $execution->perform($operation, $command, $parameters);
        } finally {
            $session->replayed = $replayed;
            \MySqlMemory\Session\State\StatementCounters::received($session, false);
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_close');
        }
    }

    /**
     * Selects the marker extension from the structure, before any execution.
     */
    public function parameters(Node $node): ParameterStyle
    {
        $pending = [$node];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif (($value instanceof Parameter && $value->marker !== '?') || ($value instanceof LanguageProfile && $value->parameters === ParameterStyle::Named)) {
                return ParameterStyle::Named;
            } elseif ($value instanceof Node) {
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }

        return ParameterStyle::Native;
    }
}
