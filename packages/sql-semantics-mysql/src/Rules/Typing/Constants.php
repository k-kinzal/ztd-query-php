<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Statement\Scalar;

/**
 * Evaluates the integer constants the server folds while it resolves the type of an expression.
 *
 * Unary minus takes its type from the value of a constant integer operand: a negative one, read
 * as a signed BIGINT, makes the negation a DECIMAL, so that negating the smallest BIGINT keeps
 * its value. A constant is written without columns, variables, parameters or functions:
 * integer and boolean literals, the operators of integer arithmetic and bit operators over
 * them, CAST to SIGNED or UNSIGNED, and a subquery that selects one constant without a table.
 * The value is the 64 bits of the integer, as the server holds it; a value that overflows, a
 * division by zero and any other expression evaluate to nothing.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html#operator_unary-minus,
 * https://dev.mysql.com/doc/refman/8.4/en/precision-math-expressions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Constants
{
    /**
     * Tells whether the operand of a unary minus is an integer constant whose value is negative read as a signed BIGINT.
     *
     * A number literal does not count: the server reads its negation as a negative literal.
     */
    public function negative(Scalar $node): bool
    {
        $literal = $node;
        while ($literal instanceof Grouped) {
            $literal = $literal->operand;
        }
        $value = $literal instanceof NumberLiteral ? null : $this->value($node);

        return $value !== null && $value[0] < 0;
    }

    /**
     * Answers the 64 bits of an integer constant and whether it is unsigned, or null when the expression is none.
     *
     * @return array{int, bool}|null
     */
    public function value(Scalar $node): ?array
    {
        return match (true) {
            $node instanceof Grouped => $this->value($node->operand),
            $node instanceof BooleanLiteral => [$node->value ? 1 : 0, false],
            $node instanceof NumberLiteral => $this->literal($node, false),
            $node instanceof SignedLiteral => $this->literal($node->number, $node->negative),
            $node instanceof Unary => $this->unary($node),
            $node instanceof Arithmetic => $this->arithmetic($node->operator, $this->value($node->left), $this->value($node->right)),
            $node instanceof Cast => $this->cast($node),
            $node instanceof ScalarSubquery => $this->subquery($node),
            default => null,
        };
    }

    /**
     * Answers the value of an integer literal, negated when it is written with a minus sign.
     *
     * A literal beyond the signed range is an unsigned BIGINT, and 9223372036854775808 negated is the
     * smallest BIGINT; any other negated literal beyond the signed range, and a literal beyond the
     * unsigned range, are decimals and evaluate to nothing.
     *
     * @return array{int, bool}|null
     */
    public function literal(NumberLiteral $literal, bool $negative): ?array
    {
        if ($literal->form !== NumberForm::Integer) {
            return null;
        }
        $digits = ltrim($literal->text, '0');
        if ($negative && $digits === '9223372036854775808') {
            return [PHP_INT_MIN, false];
        }
        if ($literal->beyondSigned()) {
            return $negative || strlen($digits) > 20 || (strlen($digits) === 20 && strcmp($digits, '18446744073709551615') > 0) ? null : [PHP_INT_MIN + $this->above($digits), true];
        }

        return [($negative ? -1 : 1) * (int) $digits, false];
    }

    /**
     * Answers how far an unsigned integer written in digits lies above the signed BIGINT range, for a value from 2^63 to 2^64 - 1.
     */
    public function above(string $digits): int
    {
        $tens = (int) substr($digits, 0, -1) - 922337203685477580;
        $last = (int) substr($digits, -1);

        return $tens === 0 ? $last - 8 : ($tens - 1) * 10 + $last + 2;
    }

    /**
     * Answers the value of a prefix operator over a constant.
     *
     * @return array{int, bool}|null
     */
    public function unary(Unary $node): ?array
    {
        $literal = $node->operand;
        while ($literal instanceof Grouped) {
            $literal = $literal->operand;
        }
        if ($node->operator === UnaryOperator::Minus && $literal instanceof NumberLiteral) {
            return $this->literal($literal, true);
        }
        $operand = $this->value($node->operand);
        if ($operand === null) {
            return null;
        }
        [$bits, $unsigned] = $operand;

        return match ($node->operator) {
            UnaryOperator::Plus => $operand,
            UnaryOperator::Minus => $bits < 0 ? null : [-$bits, false],
            UnaryOperator::Invert => [~$bits, true],
            UnaryOperator::Not => [$bits === 0 ? 1 : 0, false],
        };
    }

    /**
     * Answers the value of an arithmetic or bit operator over two constants.
     *
     * Arithmetic with an unsigned operand is unsigned, and fails below zero.
     *
     * @param array{int, bool}|null $left
     * @param array{int, bool}|null $right
     * @return array{int, bool}|null
     */
    public function arithmetic(ArithmeticOperator $operator, ?array $left, ?array $right): ?array
    {
        if ($left === null || $right === null) {
            return null;
        }
        [$a, $leftUnsigned] = $left;
        [$b, $rightUnsigned] = $right;
        $unsigned = $leftUnsigned || $rightUnsigned;
        $shift = $b < 0 || $b > 63 ? 64 : $b;
        $bits = match ($operator) {
            ArithmeticOperator::BitOr => $a | $b,
            ArithmeticOperator::BitAnd => $a & $b,
            ArithmeticOperator::BitXor => $a ^ $b,
            ArithmeticOperator::ShiftLeft => $shift === 64 ? 0 : $a << $shift,
            ArithmeticOperator::ShiftRight => $shift === 64 ? 0 : ($shift === 0 ? $a : ($a >> $shift) & (PHP_INT_MAX >> ($shift - 1))),
            ArithmeticOperator::Divide, ArithmeticOperator::Plus, ArithmeticOperator::Minus, ArithmeticOperator::Multiply, ArithmeticOperator::IntegerDivide, ArithmeticOperator::Modulo => null,
        };
        if ($bits !== null) {
            return [$bits, true];
        }
        if (($leftUnsigned && $a < 0) || ($rightUnsigned && $b < 0)) {
            return $this->beyond($operator, $left, $right);
        }
        if ((($operator === ArithmeticOperator::IntegerDivide || $operator === ArithmeticOperator::Modulo) && $b === 0)) {
            return null;
        }
        $result = match ($operator) {
            ArithmeticOperator::Plus => $a + $b,
            ArithmeticOperator::Minus => $a - $b,
            ArithmeticOperator::Multiply => $a * $b,
            ArithmeticOperator::IntegerDivide => $a === PHP_INT_MIN && $b === -1 ? null : intdiv($a, $b),
            ArithmeticOperator::Modulo => $b === -1 ? 0 : $a % $b,
            ArithmeticOperator::Divide => null,
        };

        return is_int($result) && (!$unsigned || $result >= 0) ? [$result, $unsigned] : null;
    }

    /**
     * Answers the value of adding to or subtracting from an unsigned constant beyond the signed range a value that keeps it there, or null otherwise.
     *
     * @param array{int, bool} $left
     * @param array{int, bool} $right
     * @return array{int, bool}|null
     */
    public function beyond(ArithmeticOperator $operator, array $left, array $right): ?array
    {
        [$big, $small] = $left[0] < 0 && $left[1] ? [$left, $right] : [$right, $left];
        if (($small[1] && $small[0] < 0) || ($operator === ArithmeticOperator::Minus && $big !== $left)) {
            return null;
        }
        $result = match ($operator) {
            ArithmeticOperator::Plus => $big[0] + $small[0],
            ArithmeticOperator::Minus => $big[0] - $small[0],
            ArithmeticOperator::Multiply, ArithmeticOperator::Divide, ArithmeticOperator::IntegerDivide, ArithmeticOperator::Modulo,
            ArithmeticOperator::BitOr, ArithmeticOperator::BitAnd, ArithmeticOperator::BitXor, ArithmeticOperator::ShiftLeft, ArithmeticOperator::ShiftRight => null,
        };

        return is_int($result) && $result < 0 ? [$result, true] : null;
    }

    /**
     * Answers the value of a CAST of a constant to SIGNED or UNSIGNED: its bits, read as the target says.
     *
     * @return array{int, bool}|null
     */
    public function cast(Cast $node): ?array
    {
        $operand = $node->array ? null : $this->value($node->operand);
        if ($operand === null) {
            return null;
        }

        return match ($node->target->kind) {
            CastKind::Signed => [$operand[0], false],
            CastKind::Unsigned => [$operand[0], true],
            CastKind::Binary, CastKind::Char, CastKind::NationalChar, CastKind::Date, CastKind::Time, CastKind::DateTime, CastKind::Decimal, CastKind::Json, CastKind::Year,
            CastKind::Real, CastKind::Double, CastKind::Float, CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiLineString,
            CastKind::MultiPolygon, CastKind::GeometryCollection => null,
        };
    }

    /**
     * Answers the value of a subquery that selects a single constant without a table.
     *
     * @return array{int, bool}|null
     */
    public function subquery(ScalarSubquery $node): ?array
    {
        $query = $node->query;
        while ($query instanceof ParenthesizedQuery || ($query instanceof QueryExpression && $query->with === null && $query->orderBy === [] && $query->limit === null)) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        if (!$query instanceof Select || ($query->from !== null && !$query->from instanceof Dual) || $query->where !== null || $query->groupBy !== null || $query->having !== null || $query->limit !== null || count($query->items) !== 1) {
            return null;
        }
        $item = $query->items[0];

        return $item instanceof SelectExpression ? $this->value($item->expression) : null;
    }
}
