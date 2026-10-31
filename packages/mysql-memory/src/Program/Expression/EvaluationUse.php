<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Expression;

use LogicException;
use MySqlMemory\Session\Session;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation;

/**
 * Prepares complete stored DO operands when execution reaches the instruction.
 *
 * Wildcard expansion and operand dependencies remain separate. No operand is
 * evaluated before preparation determines that each item has a scalar role.
 *
 * @visibility MySqlMemory
 */
final class EvaluationUse
{
    /**
     * Raises the first preparation condition of the retained items.
     *
     * @throws \MySqlMemory\Error\SqlError When expansion or operand preparation is refused
     * @throws LogicException When a deferred instruction has no preparation condition
     */
    public function raise(DeferredEvaluation $statement, Session $session): never
    {
        \MySqlMemory\Session\State\StatementCounters::query($session);
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_do');
        $session->diagnostics->clear();
        $database = $session->variables->database;
        $context = $session->semantics()->context(\MySqlMemory\Session\Statement\Catalog::declarations($session), true, $database === '' ? null : new SearchPath($database), $session->resolution());
        $statement = \SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($statement);
        $derivation = new Derivation($context);
        $statement->deriveProgram($derivation, new ProgramScope($derivation->environment()));
        (new \MySqlMemory\Program\Expression\Evaluation\Preparation())->check($statement, (new \SqlSemantics\Platform\MySql\Construction\StatementResults())->of($derivation), $session);
    }

    /**
     * Prepares a complete immediate DO using its already published semantic information.
     *
     * @throws \MySqlMemory\Error\SqlError When a retained dependency or operand cannot prepare
     */
    public function statement(Evaluation $statement, Session $session): never
    {
        (new \MySqlMemory\Program\Expression\Evaluation\Preparation())->check($statement, $statement->statementInformation(), $session);
    }

}
