<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;

/**
 * A stored PURGE BINARY LOGS BEFORE whose log cutoff operand requires invocation-time scalar preparation.
 *
 * Dependencies bind at definition time, but a row-valued use is checked only
 * when control reaches this instruction. This is never a standalone Statement.
 *
 * @visibility public
 * @example Retaining the log cutoff operand in a stored instruction
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() PURGE BINARY LOGS BEFORE ROW(1,2)');
 *     $operation->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredPurge // => true
 */
final class DeferredPurge implements ProgramStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param RowOperand|DeferredOperand $moment The complete non-scalar log cutoff operand
     * @param bool $masterLogs Whether the request uses the legacy MASTER synonym
     */
    public function __construct(public readonly RowOperand|DeferredOperand $moment, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly bool $masterLogs = false)
    {
        (new \SqlSemantics\Platform\MySql\Rules\Replication\LogMoment())->check($moment);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds dependencies without applying the reached instruction's cutoff constraint.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        $derivation->scalar($this->moment, $scope->environment);
    }

    /**
     * Writes the stored request and every retained operand.
     */
    public function render(Output $out): void
    {
        $out->keyword('PURGE', $this->masterLogs ? 'MASTER' : 'BINARY', 'LOGS', 'BEFORE');
        $out->node($this->moment);
    }
}
