<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;

/**
 * The logical negation written `NOT expr` at the precedence of the expr level (`Item_func_not`).
 *
 * It binds tighter than AND and looser than every comparison, so
 * `NOT a = b` negates the comparison. Under HIGH_NOT_PRECEDENCE the lexer
 * reads every NOT as the tight negation `!`, which is a Unary, so this form
 * does not exist in that mode and is rejected by the derivation.
 *
 * Rule: MYSQL-NOT-001. Facts: 1, 0 or NULL, an integer; NULL when the
 * operand is. Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/logical-operators.html#operator_not,
 * https://dev.mysql.com/doc/refman/8.4/en/sql-mode.html#sqlmode_high_not_precedence.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Not implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The negated expression
     */
    public function __construct(public readonly Scalar $operand)
    {
        Check::input((new Precedence())->opening($operand) >= Precedence::NEGATION, 'The operand of NOT needs a grouping to keep its place.');
    }

    /**
     * Derives the operand; the negation is NULL when the operand is.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        Check::input(!$derivation->context->profile->lexical->highNotPrecedence, 'Under HIGH_NOT_PRECEDENCE, NOT is the tight negation; use the unary NOT.');
        $operands = new Operands();
        $fact = $operands->single($derivation->scalar($this->operand, $environment), $derivation);

        return $operands->truth($fact->nullability);
    }

    /**
     * Writes NOT and the operand.
     */
    public function render(Output $out): void
    {
        $out->keyword('NOT')->node($this->operand);
    }
}
