<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Construction;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Construction\Facts;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

/**
 * Finishes local statement information and releases the temporary binding indexes.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class StatementResults
{
    /**
     * Finishes the result of a derivation of a SQL instruction or operand.
     */
    public function of(Derivation $construction): StatementSemantics
    {
        return $this->statement($construction->context, $construction->facts());
    }

    /**
     * Extracts statement-owned results without retaining the declaration context or indexes.
     */
    public function statement(AnalysisContext $context, Facts $facts): StatementSemantics
    {
        return new StatementSemantics(
            $context->profile,
            Settings::of($context)->client,
            $facts->output,
            $facts->declarations,
            $facts->diagnostics,
            $facts->notices,
            Settings::of($context)->connection,
        );
    }
}
