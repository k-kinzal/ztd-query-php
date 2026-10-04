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
 * A membership test in a list of expressions: `x [NOT] IN (e1, e2 …)` (`Item_func_in`).
 *
 * A list of one element is kept as written; the server evaluates it as the
 * equality (or inequality) with that element, which has the same facts.
 * The operand is a bit_expr (MYSQL-PRECEDENCE-001).
 *
 * Rule: MYSQL-IN-LIST-001. Facts: 1, 0 or NULL, an integer; it can be NULL
 * when the operand or an element can. The operand and every element must
 * have the same number of columns (MYSQL-OPERAND-COLUMNS-001). Terminates:
 * the operand and the elements are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html#operator_in.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the elements of a list
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a NOT IN (1, 2)');
 *     [$query->statement->where->negated, count($query->statement->where->elements)] // => [true, 2]
 */
final class InList implements Scalar
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The elements in order
     */
    public readonly array $elements;

    /**
     * @param Scalar $operand The tested expression
     * @param list<Scalar> $elements The elements in order; at least one
     * @param bool $negated Whether NOT is written before IN
     */
    public function __construct(public readonly Scalar $operand, array $elements, public readonly bool $negated = false)
    {
        Check::input((new Precedence())->fits($operand, Precedence::PREDICATE, Precedence::BIT_EXPR), 'The operand of IN needs a grouping to keep its place.');
        $this->elements = Check::listOf($elements, Scalar::class, 'An IN list has at least one element.', 1);
    }

    /**
     * Derives the operand and the elements, checks their widths and combines their NULL facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $facts = [$operand];
        $nullability = $operand->nullability;
        foreach ($this->elements as $element) {
            $fact = $derivation->scalar($element, $environment);
            $facts[] = $fact;
            $nullability = $nullability->propagate($fact->nullability);
        }
        $operands = new Operands();
        $operands->comparable($facts, $derivation);

        return $operands->truth($nullability);
    }

    /**
     * Writes the operand, the optional NOT, IN and the elements in parentheses.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->negated) {
            $out->keyword('NOT');
        }
        $out->keyword('IN')->symbol('(')->list($this->elements)->symbol(')');
    }
}
