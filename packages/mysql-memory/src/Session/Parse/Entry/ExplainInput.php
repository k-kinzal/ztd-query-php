<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse\Entry;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedConnectionExplain;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplainInto;

/**
 * Formats typed EXPLAIN option refusals without consulting input text.
 *
 * @visibility MySqlMemory
 */
final class ExplainInput
{
    /**
     * Preserves the option refusal selected by input lowering.
     */
    public function error(RefusedConnectionExplain|RefusedExplainInto $problem): SqlError
    {
        if ($problem instanceof RefusedConnectionExplain) {
            return $problem->analyze ? StatementError::NotSupportedYet->error('EXPLAIN ANALYZE FOR CONNECTION') : StatementError::ExplainIntoForConnection->error();
        }
        if ($problem->analyze) {
            return StatementError::ExplainAnalyzeInto->error();
        }
        return $problem->format === null ? StatementError::ExplainIntoImplicitFormat->error() : StatementError::ExplainIntoFormat->error(strtoupper($problem->format->value));
    }
}
