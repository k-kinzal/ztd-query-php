<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Index;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;

/**
 * An expression as the key of an index, a partition key or extended statistics.
 *
 * Mirrors the `expr` of PostgreSQL's `IndexElem`, `PartitionElem` and
 * `StatsElem`. The grammar takes a function call as written and any other
 * expression between parentheses; which of the two is written is kept. The
 * expression is derived where the columns of the relation are visible, and
 * the key has its type and NULL fact.
 * Source: https://www.postgresql.org/docs/17/sql-createindex.html.
 *
 * @visibility SqlSemantics
 * @example Refusing an operator without parentheses
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ExpressionKey(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate\NullTest(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a')]), false), false) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class ExpressionKey implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $expression The expression
     * @param bool $parenthesized Whether the expression is written between parentheses; one that is not must be a function call
     */
    public function __construct(public readonly Scalar $expression, public readonly bool $parenthesized = true)
    {
        Check::input(
            $parenthesized || ((new Precedence())->primary($expression) && !$expression instanceof ColumnReference && !$expression instanceof Grouped),
            'A key expression other than a function call is written between parentheses.',
        );
    }

    /**
     * Derives the expression and answers its facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->expression, $environment);

        return new ScalarFact($fact->type, $fact->nullability);
    }

    /**
     * Writes the expression, between parentheses when it is written so.
     */
    public function render(Output $out): void
    {
        if ($this->parenthesized) {
            $out->symbol('(')->node($this->expression)->symbol(')');
        } else {
            $out->node($this->expression);
        }
    }
}
