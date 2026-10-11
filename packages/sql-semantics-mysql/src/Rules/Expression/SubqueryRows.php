<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Construction\Derivation;
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
     * reports the full BIGINT width, while its negation reports width 1 (verified through PDO).
     */
    public function predicate(ScalarFact $operand, QueryFact $query, Derivation $derivation, Scalar $expression, bool $negated): ScalarFact
    {
        $nullability = $this->test($operand, $query, $derivation, $expression);
        if (($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery || $expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison) && (new ScalarReduction())->expression($expression->query) === null) {
            $nullability = Nullability::Nullable;
        }
        $legacy = in_array($derivation->context->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        $wide = $legacy && !$negated && (new Operands())->width($operand) > 1;

        return new ScalarFact(new Known(Domain::integer(ResolvedField::LongLong, $wide ? 21 : 1)), $nullability);
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
