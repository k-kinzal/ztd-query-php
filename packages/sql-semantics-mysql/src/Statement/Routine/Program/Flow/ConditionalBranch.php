<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow;

use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Routine\StatementSequence;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;

/**
 * One branch of IF or CASE: an expression, THEN, and the statements that run when the branch is chosen.
 *
 * In IF and in a searched CASE the expression is a search condition; in a
 * simple CASE it is the value compared with the operand of the CASE.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/if.html,
 * https://dev.mysql.com/doc/refman/8.4/en/case.html.
 *
 * @visibility SqlSemantics
 */
final class ConditionalBranch implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<ProgramStatement|Statement> The statements in written order
     */
    public readonly array $statements;

    /**
     * @param Scalar $condition The search condition, or the compared value of a simple CASE
     * @param list<ProgramStatement|Statement> $statements The statements after THEN; at least one
     * @throws InvalidConstruction When there is no statement or a member is of another class
     */
    public function __construct(public readonly Scalar $condition, array $statements)
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
