<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Fact;

/**
 * A diagnostic whose specific rule can report a warning.
 *
 * @visibility SqlSemantics
 */
interface WarningDiagnostic extends Diagnostic
{
    /**
     * Reports whether this diagnostic allows binding to proceed.
     */
    public function warning(): bool;
}
