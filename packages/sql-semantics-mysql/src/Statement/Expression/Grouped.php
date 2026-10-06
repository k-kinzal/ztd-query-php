<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * An expression in grouping parentheses: `( expr )`.
 *
 * The grouping is kept as written: it decides how the operators around it
 * associate, and it is closed on both edges.
 *
 * Rule: MYSQL-GROUPED-001. Facts: those of the grouped expression.
 * Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/operator-precedence.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Keeping a grouping that changes the association
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE a AND (b OR c)');
 *     $query->statement->where->right instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped // => true
 */
final class Grouped implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The grouped expression
     */
    public function __construct(public readonly Scalar $operand)
    {
    }

    /**
     * Derives the operand; the grouping has its facts.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);

        return new ScalarFact($fact->type, $fact->nullability);
    }

    /**
     * Writes the operand in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->operand)->symbol(')');
    }
}
