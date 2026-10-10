<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Predicate;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
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
 * A regular expression match: `x [NOT] REGEXP pattern` (`Item_func_regex`, `Item_func_regexp_like` in 8.0 and later).
 *
 * RLIKE is the same keyword to the lexer and is written REGEXP. Both
 * operands are bit_expr (MYSQL-PRECEDENCE-001).
 *
 * Rule: MYSQL-REGEXP-001. Facts: 1, 0 or NULL, an integer; it can be NULL
 * when an operand can. MySQL 5.6 and 5.7 evaluate a constant pattern when
 * they resolve the statement: the match is then NULL only when the matched
 * expression or the value of the pattern is, and always may be NULL when the
 * pattern varies by row; so a match of a NOT NULL expression depends on the
 * pattern (verified on live 5.6.51 and 5.7.44 servers). Both operands take
 * a single value. Terminates: the operands are strict parts.
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
     * Derives both operands and combines their NULL facts; in MySQL 5.6 and 5.7 a match of a NOT NULL expression depends on the pattern.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $operand = $operands->single($derivation->scalar($this->operand, $environment), $derivation);
        $pattern = $operands->single($derivation->scalar($this->pattern, $environment), $derivation);
        $operands->collated([$operand, $pattern], 'regexp_like', $derivation);

        $legacy = in_array($derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);

        return $operands->truth($legacy && $operand->nullability === Nullability::NotNull ? Nullability::Dependent : $operand->nullability->propagate($pattern->nullability));
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
