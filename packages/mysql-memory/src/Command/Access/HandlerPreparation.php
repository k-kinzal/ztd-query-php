<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Reading;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Statement\Operation;

/**
 * Orders handler preparation before opening its cursor's relation.
 *
 * Calls need a database before LIMIT names are resolved. The semantic restriction
 * on stored functions follows LIMIT and precedes handler, index and column lookup.
 * Verified with missing and present functions on MySQL 5.6 through 9.1.
 *
 * @visibility MySqlMemory
 */
final class HandlerPreparation
{
    /**
     * Raises preparation conditions from semantic operands and facts only.
     *
     * @throws \MySqlMemory\Error\SqlError When an operand is refused before handler lookup
     */
    public function check(Operation $operation, Session $session): void
    {
        $statement = $operation->statement;
        assert($statement instanceof HandlerScan || $statement instanceof HandlerIndexRead || $statement instanceof HandlerIndexSeek);
        $reading = new Reading();
        $reading->limited($statement->limit, $session);
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof NotSupportedYet && $diagnostic->feature === 'stored functions in HANDLER ... READ') {
                throw (new Errors())->error($diagnostic, $session);
            }
        }
    }

    /**
     * Resolves the default database of stored calls before later LIMIT names.
     *
     * @throws \MySqlMemory\Error\SqlError When an unqualified stored call has no database
     */
    public function database(Operation $operation, Session $session): void
    {
        $statement = $operation->statement;
        if (!$statement instanceof HandlerScan && !$statement instanceof HandlerIndexRead && !$statement instanceof HandlerIndexSeek) {
            return;
        }
        if ($session->variables->database === '') {
            foreach ((new Walker())->find($statement, FunctionCall::class) as $call) {
                if ($call->schema === null && !$call->native($operation->context->profile->grammar)) {
                    throw (new Errors())->routine($call, $session);
                }
            }
        }
    }

}
