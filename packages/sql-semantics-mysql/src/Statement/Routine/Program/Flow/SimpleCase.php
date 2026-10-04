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
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * The simple CASE statement: an operand compared for equality with the value of each WHEN.
 *
 * It differs from the CASE expression: it runs statements, ends with END
 * CASE and fails when no branch matches and there is no ELSE. The facts
 * follow MYSQL-PROGRAM-FLOW-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/case.html.
 *
 * @visibility public
 * @example Reading a simple CASE statement
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p(a INT) CASE a WHEN 1 THEN SELECT 1; ELSE SELECT 2; END CASE');
 *     [count($create->statement->body->branches), count($create->statement->body->otherwise)] // => [1, 1]
 */
final class SimpleCase implements ProgramStatement
{
    use Snapshot;

    /**
     * @var non-empty-list<ConditionalBranch> The WHEN branches in written order
     */
    public readonly array $branches;

    /**
     * @var list<ProgramStatement|Statement> The statements of ELSE; empty without ELSE
     */
    public readonly array $otherwise;

    /**
     * @param Scalar $operand The value every WHEN value is compared with
     * @param list<ConditionalBranch> $branches The WHEN branches; at least one
     * @param list<ProgramStatement|Statement> $otherwise The statements of ELSE; empty without ELSE
     * @throws InvalidConstruction When there is no branch or a member is of another class
     */
    public function __construct(public readonly Scalar $operand, array $branches, array $otherwise = [])
    {
        $this->branches = Check::listOf($branches, ConditionalBranch::class, 'CASE holds at least one WHEN branch.', 1);
        $this->otherwise = (new StatementSequence())->members($otherwise);
    }

    /**
     * Derives the operand, the compared values and the statements of every branch.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        $derivation->scalar($this->operand, $scope->environment);
        (new FlowFacts())->branches($this->branches, $this->otherwise, $derivation, $scope);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CASE')->node($this->operand);
        foreach ($this->branches as $branch) {
            $out->keyword('WHEN')->node($branch);
        }
        if ($this->otherwise !== []) {
            $out->keyword('ELSE');
            (new StatementSequence())->write($out, $this->otherwise);
        }
        $out->keyword('END', 'CASE');
    }
}
