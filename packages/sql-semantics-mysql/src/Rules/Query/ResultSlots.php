<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Expression\TypeAggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\Collations;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\ValueCountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountedList;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\CountMismatch;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain as ResolvedDomain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field as ResolvedField;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

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
 * output is open. Each row of VALUES is checked before its values are
 * derived, a row of another length than the first reported with its number
 * and an empty row reported, unless the statement writes the rows
 * (Derivation::writes(), as INSERT writes its source): then a row has one
 * value per written column or, where the statement allows, none to write
 * the defaults, and DEFAULT stands for the default of the column it is
 * written to (verified on a live 8.4 server). Source: https://dev.mysql.com/doc/refman/8.4/en/union.html
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
     * @param array{bool, bool} $combined Whether each operand is itself a set operation, whose rows are already in a temporary table
     * @return list<Field|OpenStar>
     */
    public function combine(QueryFact $left, QueryFact $right, SetOperator $operator, Derivation $derivation, array $combined = [false, false]): array
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
                $type = $this->settled($slot->type, $other->type, $derivation, $combined);
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
     * Answers the type of a column of a set operation: the resolved type both operands settle on in the temporary table, else the class they aggregate to.
     *
     * The server settles the columns of all the operands of nested set operations at once, so a
     * TEXT or BLOB column of an operand that is itself a set operation, which already counts its
     * length in bytes, is counted in characters again (verified on a live 8.4 server).
     *
     * @param array{bool, bool} $combined Whether each operand is itself a set operation
     */
    public function settled(TypeFact $left, TypeFact $right, Derivation $derivation, array $combined = [false, false]): TypeFact
    {
        $domains = (new Precision())->all([$left, $right]);
        if ($domains !== null) {
            $domains = array_map(static fn (ResolvedDomain $domain, bool $combined): ResolvedDomain => $combined && $domain->kind === Kind::String && $domain->field === ResolvedField::Blob ? (new Materialization())->text($domain, intdiv($domain->length, $domain->collation->charset->maxLength)) : $domain, $domains, $combined);
        }
        $domain = $domains === null ? null : (new Aggregation(new Collations(Settings::of($derivation->context)->connection)))->of($domains, 'UNION', $derivation);

        return $domain === null ? (new TypeAggregation())->aggregate([$left, $right]) : new Known((new Materialization())->set($domain));
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
        $target = $derivation->written($values);
        foreach ($values->rows as $index => $row) {
            $count = count($row->values);
            if ($target !== null && $target[0] !== [] && ($count !== 0 || !$target[1])) {
                if ($count !== count($target[0])) {
                    $derivation->report(new ValueCountMismatch(count($target[0]), $count, $index + 1));
                }
            } elseif ($count !== $width) {
                $derivation->report($target === null ? new CountMismatch(CountedList::ValueRows, $width, $count, $index + 1) : new ValueCountMismatch($width, $count, $index + 1));
            } elseif ($count === 0 && $target === null) {
                $derivation->report(new Misuse(MisuseRule::EmptyValuesRow));
            }
            foreach ($row->values as $position => $value) {
                $environment = new Environment($derivation->context, $outer);
                if ($value instanceof DefaultRequest && isset($target[0][$position])) {
                    $environment = new Environment($derivation->context, $outer, [], [], [$target[0][$position]]);
                }
                $columns[$position][] = (new Operands())->single($derivation->scalar($value, $environment), $derivation);
            }
        }
        $fields = [];
        foreach ($columns as $position => $facts) {
            $nullability = Nullability::NotNull;
            foreach ($facts as $fact) {
                $nullability = $nullability->propagate($fact->nullability);
            }
            $types = array_map(static fn (ScalarFact $fact): TypeFact => $fact->type, $facts);
            $domains = (new Precision())->all($types);
            $domain = $domains === null ? null : (new Aggregation(new Collations(Settings::of($derivation->context)->connection)))->of($domains, 'VALUES', $derivation);
            $type = $domain === null ? (new TypeAggregation())->aggregate($types) : new Known($domain);
            $fields[] = new Field($position, new OutputSlot(new Name('column_' . $position), $type, $nullability));
        }

        return new QueryFact($fields, $derivation->context->columnNames);
    }
}
