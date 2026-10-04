<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Predicate;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A regular expression match: `x [NOT] REGEXP pattern` (`Item_func_regex`, `Item_func_regexp_like` in 8.0 and later).
 *
 * RLIKE is the same keyword to the lexer and is written REGEXP. Both
 * operands are bit_expr (MYSQL-PRECEDENCE-001).
 *
 * Rule: MYSQL-REGEXP-001. Facts: 1, 0 or NULL, an integer; it can be NULL
 * when an operand can. Both operands take a single value. Terminates: the
 * operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/regexp.html#operator_regexp.
 * Status: Implemented.
 *
 * @visibility public
 * @example Writing RLIKE as REGEXP
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE a NOT RLIKE '^x'")->toString() // => "SELECT a FROM t WHERE a NOT REGEXP '^x'"
 */
final class Regexp implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The matched expression
     * @param Scalar $pattern The regular expression
     * @param bool $negated Whether NOT is written before REGEXP
     */
    public function __construct(public readonly Scalar $operand, public readonly Scalar $pattern, public readonly bool $negated = false)
    {
        $precedence = new Precedence();
        Check::input($precedence->fits($operand, Precedence::PREDICATE, Precedence::BIT_EXPR), 'The operand of REGEXP needs a grouping to keep its place.');
        Check::input($precedence->admits($pattern, Precedence::BIT_EXPR), 'The pattern of REGEXP needs a grouping to keep its place.');
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
     * Writes the operand, the optional NOT, REGEXP and the pattern.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('REGEXP')->node($this->pattern);
    }
}
