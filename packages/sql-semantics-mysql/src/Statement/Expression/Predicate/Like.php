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

/**
 * A pattern match: `x [NOT] LIKE pattern [ESCAPE c]` (`Item_func_like`).
 *
 * The operand is a bit_expr; the pattern and the escape character are
 * simple_expr (MYSQL-PRECEDENCE-001). Without ESCAPE the escape character is
 * the backslash, or none under NO_BACKSLASH_ESCAPES; that default is not
 * written.
 *
 * Rule: MYSQL-LIKE-001. Facts: 1, 0 or NULL, an integer; it can be NULL
 * when the subject or pattern can. A NULL escape selects the default escape
 * character. MySQL 5.6 and 5.7 exclude the escape from the NULL fact; later
 * releases retain its nullable metadata. Every operand takes a single value. Terminates: the
 * operands are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-comparison-functions.html#operator_like.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the escape character
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE a LIKE 'x!%' ESCAPE '!'");
 *     $query->query->where->escape->value() // => '!'
 */
final class Like implements Scalar
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar $operand The matched expression
     * @param Scalar $pattern The pattern
     * @param Scalar|null $escape The escape character, when ESCAPE is written
     * @param bool $negated Whether NOT is written before LIKE
     */
    public function __construct(public readonly Scalar $operand, public readonly Scalar $pattern, public readonly ?Scalar $escape = null, public readonly bool $negated = false, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $precedence = new Precedence();
        Check::input($precedence->fits($operand, Precedence::PREDICATE, Precedence::BIT_EXPR), 'The operand of LIKE needs a grouping to keep its place.');
        Check::input($precedence->admits($pattern, Precedence::SIMPLE_EXPR), 'The pattern of LIKE needs a grouping to keep its place.');
        Check::input($escape === null || $precedence->admits($escape, Precedence::SIMPLE_EXPR), 'The escape character of LIKE needs a grouping to keep its place.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the operands and combines their NULL facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operands = new Operands();
        $operand = $operands->single($derivation->scalar($this->operand, $environment), $derivation);
        $pattern = $operands->single($derivation->scalar($this->pattern, $environment), $derivation);
        $operands->collated([$operand, $pattern], 'like', $derivation);
        $nullability = $operand->nullability->propagate($pattern->nullability);
        if ($this->escape !== null) {
            $escape = $operands->single($derivation->scalar($this->escape, $environment), $derivation);
            if (!in_array($derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
                $nullability = $nullability->propagate($escape->nullability);
            }
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
