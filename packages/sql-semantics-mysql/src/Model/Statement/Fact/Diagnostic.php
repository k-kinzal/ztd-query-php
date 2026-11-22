<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Fact;

/**
 * A semantic problem established while analyzing grammatical SQL.
 *
 * Analysis errors carry their diagnostics in a SemanticException.
 *
 * @visibility SqlSemantics
 */
interface Diagnostic
{
    /**
     * Describes the problem for a person; the concrete class is the machine-readable kind.
     */
    public function message(): string;
}
