<?php

declare(strict_types=1);

namespace SqlFixture\Platform\PostgreSql\Schema;

use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Statement\Scalar;

/**
 * Interprets the default expression text PostgreSQL's catalog reports for a column.
 *
 * The catalog hands back the expression as SQL text, so it is analyzed as the
 * single target of a SELECT and interpreted like a DEFAULT clause.
 *
 * @visibility root
 */
final class CatalogExpression
{
    /**
     * Reads expressions with the grammar of the analysis.
     */
    public function __construct(private readonly Semantics $semantics)
    {
    }

    /**
     * Returns the value of a constant expression, and null for one the server computes or that cannot be read.
     */
    public function evaluate(string $expression): int|float|bool|string|null
    {
        $scalar = $this->expression($expression);

        return $scalar === null ? null : (new DefaultExpression())->evaluate($scalar);
    }

    /**
     * Tells whether the expression draws the next value of a sequence.
     */
    public function isSequence(string $expression): bool
    {
        $scalar = $this->expression($expression);

        return $scalar !== null && (new DefaultExpression())->isSequenceCall($scalar);
    }

    /**
     * Returns the expression the text spells, or null when it does not spell exactly one.
     */
    public function expression(string $expression): ?Scalar
    {
        try {
            $operations = $this->semantics->analyzeAll('SELECT ' . $expression);
        } catch (AnalysisException) {
            return null;
        }
        $select = count($operations) === 1 ? $operations[0]->statement : null;
        $target = $select instanceof Select && count($select->targets) === 1 && $select->from === null ? $select->targets[0] : null;

        return $target instanceof ExpressionTarget ? $target->expression : null;
    }
}
