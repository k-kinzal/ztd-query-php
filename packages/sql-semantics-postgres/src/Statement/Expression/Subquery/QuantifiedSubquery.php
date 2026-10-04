<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Sublinks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A comparison with every row of a query: `a op ANY (SELECT …)`, `SOME`, `ALL`.
 *
 * Mirrors PostgreSQL's `SubLink` node of kind `ANY_SUBLINK` or `ALL_SUBLINK`
 * with its operator name.
 *
 * Rule: PG-QUANTIFIED-SUBQUERY-001. The query is derived in the environment
 * of the expression and returns one column, or as many as the fields of a row
 * constructor on the left. Facts: the type of the operator over the value
 * and the column (PG-OPERATOR-TYPING-001); the result can be NULL. The value
 * must keep its place before the operator (PG-PRECEDENCE-001).
 * Source: https://www.postgresql.org/docs/17/functions-subquery.html#FUNCTIONS-SUBQUERY-ANY-SOME. Status: Implemented.
 *
 * @visibility public
 * @example Comparing with every row
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 < ALL (SELECT 2)');
 *     [$query->field(0)->expression->comparator->name->value, $query->field(0)->type->descriptor->name()] // => ['<', 'boolean']
 */
final class QuantifiedSubquery implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The compared value
     * @param OperatorName|MatchKeyword $comparator The operator
     * @param Quantifier $quantifier The quantifier
     * @param Query $query The query inside the parentheses
     */
    public function __construct(public readonly Scalar $operand, public readonly OperatorName|MatchKeyword $comparator, public readonly Quantifier $quantifier, public readonly Query $query)
    {
        Check::input(!$comparator instanceof OperatorName || $comparator->explicit || $comparator->qualifiers === [], 'An expression writes a qualified operator with OPERATOR(...).');
        $precedence = new Precedence();
        Check::input($precedence->before($operand, $comparator instanceof OperatorName ? $precedence->operator($comparator) : Precedence::PATTERN), 'The compared value needs parentheses to keep its place.');
    }

    /**
     * Derives the value and the query, and the comparison of the value with its rows.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $operand = $derivation->scalar($this->operand, $environment);
        $fact = $derivation->query($this->query, $environment);
        $operator = $this->comparator instanceof OperatorName ? $this->comparator : new OperatorName(new Name($this->comparator->operator()));

        return new ScalarFact((new Sublinks())->compared($derivation, $operand->type, $operator, $fact), Nullability::Nullable);
    }

    /**
     * Writes the value, the operator, the quantifier and the query in parentheses.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand);
        if ($this->comparator instanceof OperatorName) {
            $out->node($this->comparator);
        } else {
            $out->keyword(...explode(' ', $this->comparator->value));
        }
        $out->keyword($this->quantifier->value)->symbol('(')->node($this->query)->symbol(')');
    }
}
