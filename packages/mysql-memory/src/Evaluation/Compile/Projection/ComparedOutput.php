<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Projection;

use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Retains expression and stored-column conversion roles across quantified comparisons.
 *
 * Ordered quantification reads its inner result as a stored value. Its outer
 * binary literal keeps numeric interpretation only when every projected source
 * is constant. Membership caches the outer value but retains direct inner
 * literal interpretation, including set branches. Verified with native queries;
 * reference identities and projection fields supply all dependencies.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class ComparedOutput
{
    /**
     * @param Compiler $compiler The bound expression compiler
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Selects conversion roles without changing either operand's value.
     */
    public function comparator(Domain $left, Domain $right, Query $query, ComparisonOperator $operator): Comparator
    {
        $membership = $operator === ComparisonOperator::Equal || $operator === ComparisonOperator::NotEqual;
        $left = $left->withNumericBytes($left->numericBytes && !$membership && $this->fixed($query));
        $right = $right->withNumericBytes($membership && $this->binary($query));
        return Comparator::of($left, $right, $operator->value, $this->compiler->settings->connectionCollation, $this->compiler->settings->release());
    }

    /**
     * Tells whether all output origins are expressions known during preparation.
     */
    public function fixed(Query $query): bool
    {
        $expressions = (new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\ProjectionSources())->expressions($query, 0, true);
        return $expressions !== [] && !in_array(false, array_map(fn (?Scalar $expression): bool => $expression !== null && $this->compiler->constancy($expression) === Constancy::Resolved, $expressions), true);
    }

    /**
     * Tells whether each direct output is an unintroduced hexadecimal or bit literal.
     */
    public function binary(Query $query): bool
    {
        $expressions = (new \SqlSemantics\Platform\MySql\Rules\Expression\Shape\ProjectionSources())->expressions($query, 0, false);
        return $expressions !== [] && !in_array(false, array_map(static fn (?Scalar $expression): bool => $expression instanceof RadixLiteral && $expression->introducer === null, $expressions), true);
    }

    /**
     * Reads a legacy row-independent inner value before its WHERE is applied.
     * HAVING retains the row boundary, and a varying expression must read its row.
     * MySQL 5.7 also retains false or runtime-dependent WHERE predicates.
     */
    public function constant(Query $query, \MySqlMemory\Evaluation\Scope $scope): ?\MySqlMemory\Evaluation\Evaluable
    {
        $query = $this->compiler->subqueries->innermost($query);
        if (!$this->compiler->settings->legacy() || !$query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select || $query->having !== null) {
            return null;
        }
        if ($this->compiler->settings->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744 && $query->where !== null && (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\ConstantTruth())->of($query->where) !== true) {
            return null;
        }
        $field = $query->queryInformation()->projection[0] ?? null;
        $expression = $field instanceof \SqlSemantics\Statement\Shape\Field ? $field->expression : null;
        return $expression !== null && $this->compiler->constancy($expression)->constant() ? $this->compiler->compile($expression, $scope) : null;
    }

    /**
     * Tells whether HAVING drops an unknown computed constant comparison.
     * Untyped NULL preserves its ordinary NULL result; legacy variables do so too.
     */
    public function skipNull(Query $query): bool
    {
        $query = $this->compiler->subqueries->innermost($query);
        if (!$query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select || $query->from === null || $query->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual || $query->having === null) {
            return false;
        }
        $field = $query->queryInformation()->projection[0] ?? null;
        $expression = $field instanceof \SqlSemantics\Statement\Shape\Field ? $field->expression : null;
        return $expression !== null && ($this->compiler->constancy($expression) === Constancy::Resolved || (!$this->compiler->settings->legacy() && $this->compiler->constancy($expression)->constant())) && $this->compiler->domain($expression)->kind !== \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Null;
    }

}
