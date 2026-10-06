<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Routine\FlowFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Rules\Routine\StatementSequence;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * IF ... THEN ... ELSEIF ... ELSE ... END IF.
 *
 * The first branch is the IF, every further branch an ELSEIF. The facts
 * follow MYSQL-PROGRAM-FLOW-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/if.html.
 *
 * @visibility public
 * @example Reading an IF with ELSE
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p(a INT) IF a THEN SELECT 1; ELSE SELECT 2; END IF');
 *     [count($create->statement->body->branches), count($create->statement->body->otherwise)] // => [1, 1]
 */
final class IfStatement implements ProgramStatement
{
    use Snapshot;

    /**
     * @var non-empty-list<ConditionalBranch> The IF branch and the ELSEIF branches in written order
     */
    public readonly array $branches;

    /**
     * @var list<ProgramStatement|Statement> The statements of ELSE; empty without ELSE
     */
    public readonly array $otherwise;

    /**
     * @param list<ConditionalBranch> $branches The IF branch and the ELSEIF branches; at least one
     * @param list<ProgramStatement|Statement> $otherwise The statements of ELSE; empty without ELSE
     * @throws InvalidConstruction When there is no branch or a member is of another class
     */
    public function __construct(array $branches, array $otherwise = [])
    {
        $this->branches = Check::listOf($branches, ConditionalBranch::class, 'IF holds at least one branch.', 1);
        $this->otherwise = (new StatementSequence())->members($otherwise);
    }

    /**
     * Derives the conditions and the statements of every branch.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new FlowFacts())->branches($this->branches, $this->otherwise, $derivation, $scope);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        foreach ($this->branches as $position => $branch) {
            $out->keyword($position === 0 ? 'IF' : 'ELSEIF')->node($branch);
        }
        if ($this->otherwise !== []) {
            $out->keyword('ELSE');
            (new StatementSequence())->write($out, $this->otherwise);
        }
        $out->keyword('END', 'IF');
    }
}
