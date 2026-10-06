<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Derives the flow control statements of stored programs.
 *
 * Rule: MYSQL-PROGRAM-FLOW-001. The expression of every IF, ELSEIF, WHEN,
 * WHILE, UNTIL and RETURN is derived in the scope of the statement, where
 * parameters and local variables resolve as values; the nested statements
 * are derived in the same scope, inside a loop with the label of the loop
 * added (MYSQL-PROGRAM-LABELS-001). LEAVE needs an enclosing block or loop
 * with its label, ITERATE an enclosing loop (ER_SP_LILABEL_MISMATCH).
 * RETURN outside a stored function is reported (ER_SP_BADRETURN).
 * Terminates: nested statements are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/flow-control-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class FlowFacts
{
    /**
     * Derives the branches of IF or CASE and the statements of ELSE.
     *
     * @param list<ConditionalBranch> $branches
     * @param list<Node> $otherwise
     */
    public function branches(array $branches, array $otherwise, Derivation $derivation, ProgramScope $scope): void
    {
        foreach ($branches as $branch) {
            $derivation->scalar($branch->condition, $scope->environment);
            (new BodyFacts())->statements($branch->statements, $derivation, $scope);
        }
        (new BodyFacts())->statements($otherwise, $derivation, $scope);
    }

    /**
     * Derives a loop: its labels, its condition when it has one, and its statements.
     *
     * @param list<Node> $statements
     */
    public function loop(?Name $label, ?Name $end, ?Scalar $condition, array $statements, Derivation $derivation, ProgramScope $scope): void
    {
        $inside = (new LabelFacts())->enter($label, $end, true, $derivation, $scope);
        if ($condition !== null) {
            $derivation->scalar($condition, $inside->environment);
        }
        (new BodyFacts())->statements($statements, $derivation, $inside);
    }

    /**
     * Checks the label of LEAVE, or of ITERATE when a loop is required.
     */
    public function jump(Name $label, bool $iterate, Derivation $derivation, ProgramScope $scope): void
    {
        if ($scope->holds($scope->loops, $label) || (!$iterate && $scope->holds($scope->blocks, $label))) {
            return;
        }
        $derivation->report(new ProgramProblem($iterate ? ProgramRule::IterateWithoutLabel : ProgramRule::LeaveWithoutLabel, $label->value));
    }

    /**
     * Derives the expression of RETURN and checks that the program is a function.
     */
    public function returned(Scalar $value, Derivation $derivation, ProgramScope $scope): void
    {
        $derivation->scalar($value, $scope->environment);
        if ($scope->kind !== ProgramKind::Function) {
            $derivation->report(new ProgramProblem(ProgramRule::ReturnOutsideFunction));
        }
    }
}
