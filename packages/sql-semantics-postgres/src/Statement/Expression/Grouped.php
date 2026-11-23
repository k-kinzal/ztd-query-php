<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\ColumnNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;

/**
 * An expression written in parentheses: `( a_expr )`.
 *
 * The raw parser of PostgreSQL keeps no node for the parentheses, but they
 * fix the evaluation structure of what they enclose, so the model keeps them
 * as written; rendering never adds or removes a grouping.
 *
 * Rule: PG-GROUPED-001. The type, the NULL fact and the output name are those
 * of the operand. A resolved column or output field keeps its identity. A query in
 * parentheses is a scalar subquery, and more parentheses around it belong to
 * the query, so a grouping never encloses a scalar subquery.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Grouped implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @param Scalar $operand The enclosed expression; not a scalar subquery
     */
    public function __construct(public readonly Scalar $operand)
    {
        Check::input(!$operand instanceof ScalarSubquery, 'Parentheses around a scalar subquery belong to its query.');
    }

    /**
     * Names an unaliased result column as the operand does.
     */
    public function outputName(): ?Name
    {
        return (new ColumnNaming())->name($this->operand);
    }

    /**
     * Derives the operand; the grouping has its type and NULL fact.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = $derivation->scalar($this->operand, $environment);

        $resolution = $fact->resolution;

        return new ScalarFact(
            $fact->type,
            $fact->nullability,
            $resolution instanceof ResolvedColumn || $resolution instanceof AliasTarget ? $resolution : null,
        );
    }

    /**
     * Writes the operand in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->operand)->symbol(')');
    }
}
