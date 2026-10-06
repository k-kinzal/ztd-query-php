<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

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
 * @visibility public
 * @example Testing a comparison
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a = 1 IS NOT TRUE');
 *     [$query->statement->where->negated, $query->statement->where->truth->value] // => [true, 'TRUE']
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
