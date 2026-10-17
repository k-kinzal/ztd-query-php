<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored KILL whose process operand requires invocation-time scalar preparation.
 *
 * Dependencies bind at definition time, but a row-valued use is checked only
 * when control reaches this instruction. This is never a standalone Statement.
 *
 * @visibility public
 * @example Retaining the process operand in a stored instruction
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() KILL ROW(1,2)');
 *     $operation->statement->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredKill // => true
 */
final class DeferredKill implements ProgramStatement
{
    use Snapshot;

    /**
     * @param KillScope|null $scope The optional connection or query modifier
     * @param RowOperand|DeferredOperand $process The complete non-scalar process operand
     */
    public function __construct(public readonly ?KillScope $scope, public readonly RowOperand|DeferredOperand $process)
    {
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds dependencies without applying the reached instruction's process constraint.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        $derivation->scalar($this->process, $scope->environment);
    }

    /**
     * Writes the stored request and every retained operand.
     */
    public function render(Output $out): void
    {
        $out->keyword('KILL');
        if ($this->scope !== null) {
            $out->keyword($this->scope->value);
        }
        $out->node($this->process);
    }
}
