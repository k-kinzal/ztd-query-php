<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Routine\FlowFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;

/**
 * RETURN: ends a stored function and returns the value of an expression.
 *
 * The facts follow MYSQL-PROGRAM-FLOW-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/return.html.
 *
 * @visibility SqlSemantics
 */
final class ReturnStatement implements ProgramStatement
{
    use Snapshot;

    /**
     * @param Scalar $value The returned expression
     */
    public function __construct(public readonly Scalar $value)
    {
    }

    /**
     * Derives the expression and checks that the program is a function.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new FlowFacts())->returned($this->value, $derivation, $scope);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('RETURN')->node($this->value);
    }
}
