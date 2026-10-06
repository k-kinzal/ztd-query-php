<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Branching;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One `WHEN condition THEN result` branch of a CASE expression.
 *
 * In a simple CASE the condition is a value compared with the operand; in
 * a searched CASE it is a truth value. The CASE expression derives both parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-functions.html#operator_case.
 *
 * @visibility public
 * @example Reading a branch
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE CASE WHEN a THEN 1 END');
 *     $query->statement->where->branches[0]->result->text // => '1'
 */
final class CaseBranch implements Node
{
    use Snapshot;

    /**
     * @param Scalar $condition The value or condition after WHEN
     * @param Scalar $result The result after THEN
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
