<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Sublinks;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;

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
 * @visibility SqlSemantics
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
