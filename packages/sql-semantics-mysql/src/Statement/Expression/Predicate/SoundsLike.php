<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Predicate;

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
 * A comparison of the SOUNDEX strings of two operands: `x SOUNDS LIKE y`, which the server reads as `SOUNDEX(x) = SOUNDEX(y)`.
 *
 * Both operands are bit_expr (MYSQL-PRECEDENCE-001); there is no negated form.
 *
 * Rule: MYSQL-SOUNDS-LIKE-001. Facts: 1, 0 or NULL, an integer; it can be
 * NULL when an operand can. Both operands take a single value. Terminates:
 * the operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html#operator_sounds-like.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class SoundsLike implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The left operand
     * @param Scalar $pattern The right operand
     */
    public function __construct(public readonly Scalar $operand, public readonly Scalar $pattern)
    {
        $precedence = new Precedence();
        Check::input($precedence->fits($operand, Precedence::PREDICATE, Precedence::BIT_EXPR), 'The left operand of SOUNDS LIKE needs a grouping to keep its place.');
        Check::input($precedence->admits($pattern, Precedence::BIT_EXPR), 'The right operand of SOUNDS LIKE needs a grouping to keep its place.');
    }

    /**
     * Derives both operands and combines their NULL facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $operand = $operands->single($derivation->scalar($this->operand, $environment), $derivation);
        $pattern = $operands->single($derivation->scalar($this->pattern, $environment), $derivation);

        return $operands->truth($operand->nullability->propagate($pattern->nullability));
    }

    /**
     * Writes the operands around SOUNDS LIKE.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('SOUNDS', 'LIKE')->node($this->pattern);
    }
}
