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
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;

/**
 * A test of a value against a truth value: `IS [NOT] TRUE|FALSE|UNKNOWN` (`Item_func_istrue`, `Item_func_isfalse`, …).
 *
 * The test belongs to the expr level and takes a bool_pri operand, so a
 * truth test cannot test another truth test without a grouping.
 *
 * Rule: MYSQL-TRUTH-TEST-001. Facts: always 1 or 0, an integer that is
 * never NULL. Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html#operator_is.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class TruthTest implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested expression
     * @param Truth $truth The truth value compared with
     * @param bool $negated Whether NOT is written after IS
     */
    public function __construct(public readonly Scalar $operand, public readonly Truth $truth, public readonly bool $negated = false)
    {
        Check::input((new Precedence())->fits($operand, Precedence::BOOL_PRI, Precedence::BOOL_PRI), 'The operand of a truth test needs a grouping to keep its place.');
    }

    /**
     * Derives the operand; the test is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $operands->single($derivation->scalar($this->operand, $environment), $derivation);

        return $operands->truth(Nullability::NotNull);
    }

    /**
     * Writes the operand, IS, the optional NOT and the truth value.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('IS');
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword($this->truth->value);
    }
}
