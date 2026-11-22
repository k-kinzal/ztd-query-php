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
 * A NULL test: `IS [NOT] NULL` (`Item_func_isnull`, `Item_func_isnotnull`).
 *
 * The test belongs to the bool_pri level, like the comparisons, and
 * associates to the left with them.
 *
 * Rule: MYSQL-NULL-TEST-001. Facts: always 1 or 0, an integer that is never
 * NULL. A row operand is accepted (a row is NULL when every element is).
 * Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html#operator_is-null.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class NullTest implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The tested expression
     * @param bool $negated Whether NOT is written after IS
     */
    public function __construct(public readonly Scalar $operand, public readonly bool $negated = false)
    {
        Check::input((new Precedence())->fits($operand, Precedence::BOOL_PRI, Precedence::BOOL_PRI), 'The operand of a NULL test needs a grouping to keep its place.');
    }

    /**
     * Derives the operand; the test is never NULL.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        (new Operands())->single($derivation->scalar($this->operand, $environment), $derivation);

        return (new Operands())->truth(Nullability::NotNull);
    }

    /**
     * Writes the operand, IS, the optional NOT and NULL.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('IS');
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('NULL');
    }
}
