<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Routine\FlowFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;

/**
 * RETURN: ends a stored function and returns the value of an expression.
 *
 * The facts follow MYSQL-PROGRAM-FLOW-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/return.html.
 *
 * A stored row operand is retained until this instruction is reached. It cannot
 * supply a scalar result, but an unreachable instruction does not evaluate it.
 *
 * @visibility public
 * @example Reading the returned expression
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE FUNCTION f(a INT) RETURNS INT RETURN a');
 *     $create->body->value->name->value // => 'a'
 */
final class ReturnStatement implements FunctionBody
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $value The returned expression
     */
    public function __construct(public readonly Scalar|RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand $value, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
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
