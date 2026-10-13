<?php

declare(strict_types=1);

namespace Fuzz\Structure;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Execution as StatementExecution;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Statement;
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
    public function run(Statement|Scalar $node, string $release = '8.4.7', array $parameters = [], ?string $mode = null): Reply
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
     * @throws \MySqlMemory\Error\SqlError When execution rejects the structure
     */
    public function runIn(Session $session, Statement|Scalar $node, array $parameters = []): Reply
    {
        $statement = $node instanceof Statement ? $node : new Select([], [new SelectExpression($node)]);
        $domains = array_map(static fn (array $parameter): \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain => $parameter[1]->resolved(), $parameters);
        $context = $session->semantics()->context($session->instance->dictionary->declarations(), true, null, $session->resolution($domains));
        $operation = new Operation($context, $statement);

        return (new StatementExecution($session))->perform($operation, (new Dispatcher())->command($statement), $parameters);
    }
}
