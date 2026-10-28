<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;

/**
 * Executes DO: evaluates expressions and discards their values.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/do.html.
 *
 * @visibility MySqlMemory
 */
final class DoCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Evaluates each expression; like a SELECT of one row, it leaves FOUND_ROWS() at 1.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        \SqlSemantics\Diagnostic\Check::invariant($operation instanceof Evaluation, 'The DO command receives a complete evaluation statement.');
        $statement = $operation;
        $planner = new Planner($statement, $operation->statementInformation(), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));
        $frame = new Frame($context);
        foreach ($statement->items as $item) {
            \SqlSemantics\Diagnostic\Check::invariant($item instanceof \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression, 'A prepared DO item has a scalar result.');
            $planner->compiler->compile($item->expression, new Scope())->evaluate($frame);
        }
        $session->variables->foundRows = 1;

        return new Completion(0, 0, $context->diagnostics->raised());
    }
}
