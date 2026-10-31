<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow;

use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Routine\StatementSequence;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * One branch of IF or CASE: an expression, THEN, and the statements that run when the branch is chosen.
 *
 * In IF and in a searched CASE the expression is a search condition; in a
 * simple CASE it is the value compared with the operand of the CASE.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/if.html,
 * https://dev.mysql.com/doc/refman/8.4/en/case.html.
 *
 * Row operands remain stored program values. Their scalar-use condition is
 * checked when control reaches this instruction, after binding its operands.
 * They do not become ordinary Scalar expressions.
 *
 * @visibility public
 * @example Reading the branches of IF
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p(a INT) IF a = 1 THEN SELECT 1; ELSEIF a = 2 THEN SELECT 2; SELECT 3; END IF');
 *     [count($create->body->branches), count($create->body->branches[1]->statements)] // => [2, 2]
 */
final class ConditionalBranch implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<ProgramStatement|Statement> The statements in written order
     */
    public readonly array $statements;

    /**
     * @param Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $condition The search condition, or the compared value of a simple CASE
     * @param list<ProgramStatement|Statement> $statements The statements after THEN; at least one
     * @throws InvalidConstruction When there is no statement or a member is of another class
     */
    public function __construct(public readonly Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $condition, array $statements, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        $this->statements = (new StatementSequence())->members($statements, 1);
    }

    /**
     * Writes the expression, THEN and the statements.
     */
    public function render(Output $out): void
    {
        $out->node($this->condition)->keyword('THEN');
        (new StatementSequence())->write($out, $this->statements);
    }
}
