<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Derives the labels of blocks and loops.
 *
 * Rule: MYSQL-PROGRAM-LABELS-001. A label is in scope inside the statement
 * it begins. A label that an enclosing statement already uses is reported
 * (ER_SP_LABEL_REDEFINE); an end label that differs from the begin label is
 * reported (ER_SP_LABEL_MISMATCH). Labels are compared without regard to
 * letter case. Terminates: the label lists are finite.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/statement-labels.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LabelFacts
{
    /**
     * Checks the labels of a block or loop and answers the scope inside it.
     */
    public function enter(?Name $label, ?Name $end, bool $loop, Derivation $derivation, ProgramScope $scope): ProgramScope
    {
        if ($label !== null && ($scope->holds($scope->blocks, $label) || $scope->holds($scope->loops, $label))) {
            $derivation->report(new ProgramProblem(ProgramRule::RedefinedLabel, $label->value));
        }
        if ($end !== null && ($label === null || strcasecmp($label->value, $end->value) !== 0)) {
            $derivation->report(new ProgramProblem(ProgramRule::EndLabelMismatch, $end->value));
        }

        return $scope->labeled($label, $loop);
    }
}
