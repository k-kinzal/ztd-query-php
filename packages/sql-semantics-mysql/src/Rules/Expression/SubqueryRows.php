<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\ScalarFact;
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
 * column whose own metadata reports zero. A direct GROUP_CONCAT result retains its
 * fractional metadata (zero in MySQL 5.6 and 5.7, verified through SQL). A subquery with an unexpandable star depends on the inputs it
 * misses. A membership or quantified test is NULL when the operand or a
 * column of the subquery can be NULL; a subquery whose columns are not known
 * leaves that dependent. The width of the operand must equal the number of
 * columns (MYSQL-OPERAND-COLUMNS-001). Terminates: no recursion.
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
    public function value(QueryFact $query): ScalarFact
    {
        if (!$query->shape->complete()) {
            return new ScalarFact(new Dependent($query->shape->missing), Nullability::Nullable);
        }
        $slots = $query->shape->slots;
        if (count($slots) === 1) {
            $domain = (new Precision())->domain($slots[0]->type);
            if ($domain?->kind === Kind::String) {
                $field = $query->projection[0] ?? null;
                return new ScalarFact(new Known($this->string($domain, $field instanceof Field ? $field->expression : null)), Nullability::Nullable);
            }

            return new ScalarFact($domain === null || $domain->display === null ? $slots[0]->type : new Known($domain->value()), Nullability::Nullable);
        }

        return new ScalarFact(new Known(new Tuple(max(2, count($slots)))), Nullability::Nullable);
    }

    /**
     * Retains direct GROUP_CONCAT metadata and removes fixed fractional digits from other string scalar results.
     */
    public function string(Domain $domain, ?Scalar $expression): Domain
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }

        return $expression instanceof GroupConcat ? $domain : Domain::string($domain->length, $domain->collation, $domain->field, $domain->coercibility);
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
        $operands->comparable([$operand, $this->value($query)], $derivation, $expression);
        foreach ($query->shape->slots as $slot) {
            $nullability = $nullability->propagate($slot->nullability);
        }

        return $nullability;
    }
}
