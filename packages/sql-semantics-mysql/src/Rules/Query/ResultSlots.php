<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\TypeAggregation;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the output columns of set operations and VALUES statements from the columns they combine.
 *
 * Rule: MYSQL-RESULT-SLOTS-001. A set operation is named by the columns of
 * its first operand. The type of a column is the aggregation of the types
 * at its position over the operands or rows (MYSQL-TYPE-AGGREGATION, the
 * rule of the expression family). A UNION column can be NULL when it can in
 * either operand, an INTERSECT column only when it can in both, an EXCEPT
 * column when it can in the left operand. A VALUES column is named column_N,
 * counting from zero, and can be NULL when one of its values can. Operands
 * or rows of different lengths are reported, and a column one of them lacks
 * is invalid; while the first operand has columns that are not known the
 * output is open. Source: https://dev.mysql.com/doc/refman/8.4/en/union.html
 * ("Result Set Column Names and Data Types"),
 * https://dev.mysql.com/doc/refman/8.4/en/values.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ResultSlots
{
    /**
     * Combines the outputs of the two operands of a set operation.
     *
     * @return list<Field|OpenStar>
     */
    public function combine(QueryFact $left, QueryFact $right, SetOperator $operator, Derivation $derivation): array
    {
        if (!$left->shape->complete()) {
            return [new OpenStar([...$left->shape->missing, ...$right->shape->missing])];
        }
        $problem = null;
        if ($right->shape->complete() && count($left->shape->slots) !== count($right->shape->slots)) {
            $problem = new CountMismatch(CountedList::SetOperands, count($left->shape->slots), count($right->shape->slots));
            $derivation->report($problem);
        }
        $fields = [];
        foreach ($left->shape->slots as $position => $slot) {
            $other = $right->shape->complete() ? $right->shape->slots[$position] ?? null : null;
            if ($other !== null) {
                $type = (new TypeAggregation())->aggregate([$slot->type, $other->type]);
                $nullability = $this->nullability($operator, $slot->nullability, $other->nullability);
            } elseif ($problem !== null) {
                $type = new Invalid($problem);
                $nullability = Nullability::Dependent;
            } else {
                $type = new Dependent($right->shape->missing);
                $nullability = $operator === SetOperator::Except ? $slot->nullability : Nullability::Dependent;
            }
            $fields[] = new Field($position, new OutputSlot($slot->name, $type, $nullability, null, null, $slot->unnamed));
        }

        return $fields;
    }

    /**
     * Answers whether a column of a set operation can be NULL from the facts of both operands.
     */
    public function nullability(SetOperator $operator, Nullability $left, Nullability $right): Nullability
    {
        if ($operator === SetOperator::Except) {
            return $left;
        }
        if ($operator === SetOperator::Union) {
            return $left->propagate($right);
        }
        if ($left === Nullability::NotNull || $right === Nullability::NotNull) {
            return Nullability::NotNull;
        }

        return $left === Nullability::Nullable && $right === Nullability::Nullable ? Nullability::Nullable : Nullability::Dependent;
    }

    /**
     * Derives every value of a VALUES statement and answers its output.
     */
    public function values(ValuesQuery $values, Derivation $derivation, Environment $outer): QueryFact
    {
        $columns = [];
        $width = count($values->rows[0]->values);
        foreach ($values->rows as $row) {
            if (count($row->values) !== $width) {
                $derivation->report(new CountMismatch(CountedList::ValueRows, $width, count($row->values)));
            }
            foreach ($row->values as $position => $value) {
                $columns[$position][] = (new Operands())->single($derivation->scalar($value, new Environment($derivation->context, $outer)), $derivation);
            }
        }
        $fields = [];
        foreach ($columns as $position => $facts) {
            $nullability = Nullability::NotNull;
            foreach ($facts as $fact) {
                $nullability = $nullability->propagate($fact->nullability);
            }
            $type = (new TypeAggregation())->aggregate(array_map(static fn (ScalarFact $fact) => $fact->type, $facts));
            $fields[] = new Field($position, new OutputSlot(new Name('column_' . $position), $type, $nullability));
        }

        return new QueryFact($fields, $derivation->context->columnNames);
    }
}
