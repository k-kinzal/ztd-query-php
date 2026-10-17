<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A standalone KILL input whose process identifier cannot have a scalar role.
 *
 * Complete typed dependencies retain earlier name and variable failures.
 * This diagnostic is not an executable Statement and retains no SQL or AST.
 *
 * @visibility public
 * @example Refusing a standalone row-valued process identifier
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('KILL ROW(1,2)') // throws \SqlSemantics\Construction\RejectedInput
 */
final class RefusedKillOperand implements Diagnostic
{
    use Snapshot;

    /**
     * @param RowOperand|DeferredOperand $process The refused identifier and all its dependencies
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
