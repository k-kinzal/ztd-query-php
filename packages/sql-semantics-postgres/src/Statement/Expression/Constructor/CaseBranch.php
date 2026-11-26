<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One `WHEN condition THEN result` branch of a CASE expression.
 *
 * Mirrors PostgreSQL's `CaseWhen` node. With a CASE operand the condition is
 * a value compared with the operand.
 * Source: https://www.postgresql.org/docs/17/functions-conditional.html#FUNCTIONS-CASE.
 *
 * @visibility SqlSemantics
 */
final class CaseBranch implements Node
{
    use Snapshot;

    /**
     * @param Scalar $condition The condition, or the value compared with the CASE operand
     * @param Scalar $result The result when the branch is taken
     */
    public function __construct(public readonly Scalar $condition, public readonly Scalar $result)
    {
    }

    /**
     * Writes WHEN, the condition, THEN and the result.
     */
    public function render(Output $out): void
    {
        $out->keyword('WHEN')->node($this->condition)->keyword('THEN')->node($this->result);
    }
}
