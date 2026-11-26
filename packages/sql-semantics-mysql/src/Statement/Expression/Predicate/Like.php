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
 * A pattern match: `x [NOT] LIKE pattern [ESCAPE c]` (`Item_func_like`).
 *
 * The operand is a bit_expr; the pattern and the escape character are
 * simple_expr (MYSQL-PRECEDENCE-001). Without ESCAPE the escape character is
 * the backslash, or none under NO_BACKSLASH_ESCAPES; that default is not
 * written.
 *
 * Rule: MYSQL-LIKE-001. Facts: 1, 0 or NULL, an integer; it can be NULL
 * when an operand can. Every operand takes a single value. Terminates: the
 * operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-comparison-functions.html#operator_like.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Like implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The matched expression
     * @param Scalar $pattern The pattern
     * @param Scalar|null $escape The escape character, when ESCAPE is written
     * @param bool $negated Whether NOT is written before LIKE
     */
    public function __construct(public readonly Scalar $operand, public readonly Scalar $pattern, public readonly ?Scalar $escape = null, public readonly bool $negated = false)
    {
        $precedence = new Precedence();
        Check::input($precedence->fits($operand, Precedence::PREDICATE, Precedence::BIT_EXPR), 'The operand of LIKE needs a grouping to keep its place.');
        Check::input($precedence->admits($pattern, Precedence::SIMPLE_EXPR), 'The pattern of LIKE needs a grouping to keep its place.');
        Check::input($escape === null || $precedence->admits($escape, Precedence::SIMPLE_EXPR), 'The escape character of LIKE needs a grouping to keep its place.');
    }

    /**
     * Derives the operands and combines their NULL facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $nullability = $operands->single($derivation->scalar($this->operand, $environment), $derivation)->nullability
            ->propagate($operands->single($derivation->scalar($this->pattern, $environment), $derivation)->nullability);
        if ($this->escape !== null) {
            $nullability = $nullability->propagate($operands->single($derivation->scalar($this->escape, $environment), $derivation)->nullability);
        }

        return $operands->truth($nullability);
    }

    /**
     * Writes the operand, the optional NOT, LIKE, the pattern and the escape clause.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('LIKE')->node($this->pattern);
        if ($this->escape !== null) {
            $out->keyword('ESCAPE')->node($this->escape);
        }
    }
}
