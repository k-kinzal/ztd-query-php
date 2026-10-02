<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rules\Typing;

use SqlSemantics\Platform\Sqlite\Rules\Expression\Precedence;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * Derives the result facts of the SQLite operators.
 *
 * Rule: SQLITE-OPERATOR-RESULT-001. Comparisons, bitwise operators and the
 * logical operators yield INTEGER; the IS family is never NULL; AND and OR
 * can be NULL when an operand can; every other operator is NULL when an
 * operand is. Addition, subtraction and multiplication yield INTEGER or
 * REAL (integer overflow turns into REAL) and REAL when an operand is REAL.
 * Division and remainder do the same and are NULL for a zero divisor, so
 * they can always be NULL. Concatenation yields TEXT. `->` yields the TEXT
 * of a JSON value and `->>` an INTEGER, REAL or TEXT value; both are NULL
 * when the path selects nothing. Unary minus follows the arithmetic rule,
 * unary plus changes nothing, `~` and NOT yield INTEGER.
 * Source: https://sqlite.org/lang_expr.html#operators_and_parse_affecting_attributes,
 * https://sqlite.org/datatype3.html#operators, https://sqlite.org/json1.html#jptr.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class Operators
{
    /**
     * Answers the facts of a binary operation from the facts of its operands.
     */
    public function binary(BinaryOperator $operator, ScalarFact $left, ScalarFact $right): ScalarFact
    {
        $storages = new Storages();
        $both = $left->nullability->propagate($right->nullability);
        if (in_array($operator, [BinaryOperator::Is, BinaryOperator::IsNot, BinaryOperator::IsDistinctFrom, BinaryOperator::IsNotDistinctFrom], true)) {
            return new ScalarFact(new Known(Storage::Integer), Nullability::NotNull);
        }
        if ($operator === BinaryOperator::Extract) {
            return new ScalarFact($storages->strict(Storage::Text, [$left->type, $right->type]), Nullability::Nullable);
        }
        if ($operator === BinaryOperator::ExtractValue) {
            return new ScalarFact($left->type instanceof NullOnly || $right->type instanceof NullOnly ? new NullOnly() : new Choice([Storage::Integer, Storage::Real, Storage::Text]), Nullability::Nullable);
        }
        if ($operator === BinaryOperator::Divide || $operator === BinaryOperator::Modulo) {
            return new ScalarFact($storages->numeric([$left->type, $right->type]), Nullability::Nullable);
        }

        return match ($operator->level()) {
            Precedence::DISJUNCTION, Precedence::CONJUNCTION => new ScalarFact(new Known(Storage::Integer), $both),
            Precedence::ADDITIVE, Precedence::MULTIPLICATIVE => new ScalarFact($storages->numeric([$left->type, $right->type]), $both),
            Precedence::CONCATENATION => new ScalarFact($storages->strict(Storage::Text, [$left->type, $right->type]), $both),
            default => new ScalarFact($storages->strict(Storage::Integer, [$left->type, $right->type]), $both),
        };
    }

    /**
     * Answers the facts of a prefix operation from the facts of its operand.
     */
    public function unary(UnaryOperator $operator, ScalarFact $operand): ScalarFact
    {
        $storages = new Storages();

        return match ($operator) {
            UnaryOperator::Plus => new ScalarFact($operand->type, $operand->nullability),
            UnaryOperator::Minus => new ScalarFact($storages->numeric([$operand->type]), $operand->nullability),
            UnaryOperator::Not, UnaryOperator::BitNot => new ScalarFact($storages->strict(Storage::Integer, [$operand->type]), $operand->nullability),
        };
    }
}
