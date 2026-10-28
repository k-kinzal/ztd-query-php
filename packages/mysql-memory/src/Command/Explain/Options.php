<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Explain;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Session\Parse\Entry\ExplainInput;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\UnknownExplainFormat;
use SqlSemantics\Statement\Node;

/**
 * Checks retained EXPLAIN options before resolving the explained dependencies.
 *
 * MySQL 8.1 checks INTO after reading the explained operand; later releases
 * check it at the options boundary. Connection restrictions follow format and
 * destination validation. No source buffer is needed to select the refusal.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain.html.
 *
 * @visibility MySqlMemory
 */
final class Options
{
    /**
     * Raises the version's first incompatible option, if the node is an explanation.
     *
     * @throws \MySqlMemory\Error\SqlError When the server refuses an option
     */
    public function check(Node $statement, Session $session): void
    {
        if (!$statement instanceof Explain && !$statement instanceof DeferredExplain && !$statement instanceof ExplainConnection) {
            return;
        }
        $problem = (new ExplainFacts())->options($session->settings()->release(), $statement->format, $statement->analyze, $statement->into, $statement instanceof ExplainConnection);
        if ($problem instanceof UnknownExplainFormat) {
            throw StatementError::UnknownExplainFormat->error($problem->name->value);
        }
        if ($problem !== null) {
            throw (new ExplainInput())->error($problem);
        }
    }
}
