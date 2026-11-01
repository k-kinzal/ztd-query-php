<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Problem;

use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A standalone PURGE BINARY LOGS BEFORE input whose log cutoff operand cannot have a scalar role.
 *
 * Complete typed dependencies retain earlier name and variable failures.
 * This diagnostic is not an executable Statement and retains no SQL or AST.
 *
 * @visibility public
 * @example Refusing a standalone row-valued log cutoff operand
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('PURGE BINARY LOGS BEFORE ROW(1,2)') // throws \SqlSemantics\Construction\RejectedInput
 */
final class RefusedPurgeOperand implements Diagnostic
{
    use Snapshot;

    /**
     * @param RowOperand|DeferredOperand $moment The refused cutoff and all its dependencies
     * @param bool $masterLogs Whether the request uses the legacy MASTER synonym
     */
    public function __construct(public readonly RowOperand|DeferredOperand $moment, public readonly bool $masterLogs = false)
    {
        (new \SqlSemantics\Platform\MySql\Rules\Replication\LogMoment())->check($moment);
    }

    /**
     * Describes the required cutoff role.
     */
    public function message(): string
    {
        return 'A standalone PURGE BINARY LOGS BEFORE requires a scalar log cutoff operand.';
    }
}
