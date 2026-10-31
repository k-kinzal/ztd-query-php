<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A KILL process expression that requires a scalar-use preparation failure.
 *
 * Complete typed dependencies retain earlier name and variable failures.
 * This diagnostic is not an executable Statement and retains no SQL or AST.
 *
 * @visibility public
 * @example Retaining a row-valued process identifier and its condition
 *     $kill = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('KILL ROW(1,2)');
 *     [$kill->toString(), $kill->diagnostics()[0]->message()] // => ['KILL ROW(1, 2)', 'A standalone KILL requires a scalar process identifier.']
 */
final class RefusedKillOperand implements Diagnostic
{
    use Snapshot;

    /**
     * @param RowOperand|DeferredOperand $process The retained identifier and all its dependencies
     */
    public function __construct(public readonly RowOperand|DeferredOperand $process)
    {
    }

    /**
     * Describes the required process role.
     */
    public function message(): string
    {
        return 'A standalone KILL requires a scalar process identifier.';
    }
}
