<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field as ResolvedField;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the value of a subquery used as an expression and the NULL fact of a test against its rows.
 *
 * Rule: MYSQL-SUBQUERY-ROWS-001. A subquery used as a value yields the value
 * of its one column, without the display width of the column, or a row of its columns, and NULL when it returns no
 * row. An unreduced string scalar has no fixed fractional digits, including a
 * column whose own metadata reports zero. A temporal expression reported as a string is read as text
 * and retains its reported decimals, independently of the original evaluation kind.
 * A direct GROUP_CONCAT result retains its
 * fractional metadata (zero in MySQL 5.6 and 5.7, verified through SQL). A subquery with an unexpandable star depends on the inputs it
 * misses. A membership or quantified test is NULL when the operand or a
 * column of the subquery can be NULL. Multi-column membership is reported
 * nullable even for constant non-NULL rows, as observed through PDO.
 * A subquery whose columns are not known
 * leaves that dependent. The width of the operand must equal the number of
 * columns (MYSQL-OPERAND-COLUMNS-001). LIMIT is refused on the tested query
 * blocks, including set branches, independently of limits inside derived tables or CTEs.
 * Terminates: limit checks recurse only into strict query children.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/scalar-subqueries.html,
 * https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html,
 * https://dev.mysql.com/doc/refman/8.4/en/any-in-some-subqueries.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SubqueryRows
{
    /**
     * Answers the facts of a subquery used as a value.
     */
    public function value(QueryFact $query, ?Facts $facts = null): ScalarFact
    {
        if (!$query->shape->complete()) {
            return new ScalarFact(new Dependent($query->shape->missing), Nullability::Nullable);
        }
        $slots = $query->shape->slots;
        if (count($slots) === 1) {
            $domain = (new Precision())->domain($slots[0]->type);
            if ($domain?->field === ResolvedField::VarString && $domain->kind->temporal()) {
                return new ScalarFact(new Known(new Domain(Kind::String, $domain->field, $domain->length, $domain->decimals, false, $domain->collation, [], $domain->coercibility, displayDecimals: $domain->displayDecimals ?? $domain->decimals)), Nullability::Nullable);
            }
            if ($domain?->kind === Kind::String) {
                $field = $query->projection[0] ?? null;
                $expression = $field instanceof Field ? $field->expression : null;
                while ($expression instanceof Grouped) {
                    $expression = $expression->operand;
                }
                $resolution = $expression !== null && $facts?->covers($expression) === true ? $facts->scalar($expression)->resolution : ($field instanceof Field ? $field->resolution : null);

                return new ScalarFact(new Known($this->string($domain, $expression, $resolution instanceof ResolvedColumn)), Nullability::Nullable);
            }

            return new ScalarFact($domain === null || $domain->display === null ? $slots[0]->type : new Known($domain->value()), Nullability::Nullable);
        }

        return new ScalarFact(new Known(new Tuple(max(2, count($slots)))), Nullability::Nullable);
    }

    /**
     * Answers the published NULL attribute of an unreduced scalar or row subquery.
     * MySQL 5.6 does not propagate projected NULLs into a tableless unfiltered row's
     * attribute, or a tableless UNION's attribute. This describes metadata, not whether
     * a value can be NULL (verified through ordinary comparisons against both kinds of row).
     */
    public function attribute(Query $statement, QueryFact $query, GrammarRelease $release): Nullability
    {
        if (!$query->shape->complete() || $query->shape->slots === []) {
            return Nullability::Nullable;
        }
        $row = count($query->shape->slots) > 1;
        $body = $statement;
        while ($body instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery || $body instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression) {
            $body = $body instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery ? $body->query : $body->body;
        }
        if ($row && $release === GrammarRelease::MySql5651 && $body instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation && $this->tableless($body)) {
            return Nullability::NotNull;
        }
        $combined = $body instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation || $body instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
        if (!$row && $combined && in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) && $this->tableless($body, $release === GrammarRelease::MySql5744)) {
            return $query->shape->slots[0]->nullability;
        }
        if (!(new ScalarReduction())->preservesNullability($statement, $release)) {
            return Nullability::Nullable;
        }
        $nullable = Nullability::NotNull;
        if (!$row || $release !== GrammarRelease::MySql5651) {
            foreach ($query->shape->slots as $slot) {
                $nullable = $nullable->propagate($slot->nullability);
            }
        }

        return $nullable;
    }

    /**
     * Tells whether every UNION operand has no FROM relation other than DUAL.
     * The optional filter check preserves MySQL 5.7's different empty-row metadata.
     */
    public function tableless(Query|\SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion $query, bool $unfiltered = false): bool
    {
        return match (true) {
            $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery => $this->tableless($query->query, $unfiltered),
            $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression => $this->tableless($query->body, $unfiltered),
            $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation, $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation, $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion => $this->tableless($query->left, $unfiltered) && $this->tableless($query->right, $unfiltered),
            $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select => ($query->from === null || $query->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual) && (!$unfiltered || ($query->where === null && $query->having === null)),
            default => false,
        };
    }

    /**
     * Retains direct GROUP_CONCAT metadata and removes fixed fractional digits from other string scalar results.
     * A TEXT/BLOB column contributes its collation-expanded bound; a computed blob
     * already carries its result bound. Name resolution distinguishes columns from
     * expression aliases, including through parentheses and star expansion.
     * A set output with a retained capacity already describes a temporary column.
     */
    public function string(Domain $domain, ?Scalar $expression, bool $column = false): Domain
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }

        $stored = $column || ($expression === null && $domain->capacity !== null);
        $length = $stored && $domain->field->blob() ? min(4294967295, $domain->value()->byteLength()) : $domain->length;

        return $expression instanceof GroupConcat ? $domain : new Domain(Kind::String, $domain->field, $length, Domain::NOT_FIXED, false, $domain->collation, $domain->members, $domain->coercibility, $stored ? null : $domain->display);
    }

    /**
     * Answers the NULL fact of a test of an operand against the rows of a subquery, reporting a width mismatch.
     */
    public function test(ScalarFact $operand, QueryFact $query, Derivation $derivation, ?Scalar $expression = null): Nullability
    {
        $nullability = $operand->nullability;
        if (!$query->shape->complete()) {
            return $nullability->propagate(Nullability::Dependent);
        }
        $operands = new Operands();
        $operands->comparable([$operand, $this->value($query, $derivation->facts())], $derivation, $expression);
        foreach ($query->shape->slots as $slot) {
            $nullability = $nullability->propagate($slot->nullability);
        }

        return count($query->shape->slots) > 1 ? Nullability::Nullable : $nullability;
    }

    /**
     * Derives membership metadata; positive row membership in MySQL 5.6 and 5.7
     * reports the full BIGINT width, while its negation reports width 1. Other unreduced
     * quantified predicates retain only the outer operand's NULL attribute on these
     * legacy releases, even when comparison with an inner NULL yields NULL.
     * Verified through PDO metadata and row values.
     */
    public function predicate(ScalarFact $operand, QueryFact $query, Derivation $derivation, Scalar $expression, bool $negated): ScalarFact
    {
        $nullability = $this->test($operand, $query, $derivation, $expression);
        $release = $derivation->context->profile->grammar;
        $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $equal = $expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery || ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison && in_array($expression->operator, [\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal, \SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::NotEqual], true));
        if (($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery || $expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison) && ($equal ? (new ScalarReduction())->predicate($expression->query, $release) : (new ScalarReduction())->expression($expression->query, $release)) === null) {
            $nullability = $expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison ? $this->unreduced($expression, $operand->nullability, $nullability, $release, $derivation->facts()) : Nullability::Nullable;
        }
        $wide = $legacy && !$negated && ((new Operands())->width($operand) > 1 || $this->stored($expression, $derivation->context->profile->grammar) !== null);

        return new ScalarFact(new Known(Domain::integer(ResolvedField::LongLong, $wide ? 21 : 1)), $nullability);
    }

    /**
     * Retains native NULL attributes for unreduced quantified comparisons.
     * Equality with ALL and inequality with ANY retain the bound output attribute.
     * Ordered predicates over an unfiltered tableless set do so on modern servers;
     * legacy and early-modern profiles retain their distinct projection attributes.
     */
    public function unreduced(\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison $expression, Nullability $outer, Nullability $bound, GrammarRelease $release, Facts $facts): Nullability
    {
        $equal = $expression->operator === \SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal;
        $unequal = $expression->operator === \SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::NotEqual;
        $all = $expression->quantifier === \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier::All;
        if (($equal && !$all) || ($unequal && $all)) {
            return Nullability::Nullable;
        }
        if (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return (new Preparation\QuantifiedAttributes($facts))->legacy($expression->query, $outer, $bound, $release, !$equal && !$unequal);
        }
        return (new Preparation\QuantifiedAttributes($facts))->modern($expression->query, $outer, $bound, $release, $equal || $unequal);
    }

    /**
     * Answers the temporary-column domain of an unreduced positive membership test.
     * Legacy releases report unreduced positive IN and = ANY as full-width signed
     * BIGINT, both directly and through a temporary column. Negation has its own wrapper.
     * Verified through PDO metadata from MySQL 5.6.51 and 5.7.44 Testcontainers.
     */
    public function stored(?Scalar $expression, GrammarRelease $release): ?Domain
    {
        if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return null;
        }
        $membership = ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery && !$expression->negated)
            || ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison && $expression->operator === \SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal && $expression->quantifier === \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier::Any);

        return $membership && (new ScalarReduction())->predicate($expression->query, $release) === null ? Domain::integer() : null;
    }

    /**
     * Reports LIMIT on the subquery's query blocks, but not inside derived tables or CTE definitions.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/subquery-restrictions.html.
     */
    public function checkLimit(Query $query, Derivation $derivation): void
    {
        if ($this->limited($query)) {
            $derivation->report(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet('LIMIT & IN/ALL/ANY/SOME subquery'));
        }
    }

    /**
     * Tells whether a query or a branch of its set operation carries a LIMIT.
     */
    public function limited(Query|\SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion $query): bool
    {
        return match (true) {
            $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select => $query->limit !== null || $query->late?->limit !== null,
            $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery => $this->limited($query->query),
            $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression => $query->limit !== null || $this->limited($query->body),
            $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation => $query->limit !== null || $this->limited($query->left) || $this->limited($query->right),
            $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation,
            $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion => $this->limited($query->left) || $this->limited($query->right),
            default => false,
        };
    }
}
