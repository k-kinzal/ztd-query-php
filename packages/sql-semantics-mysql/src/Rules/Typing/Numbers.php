<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Scalar;

/**
 * Resolves the types of arithmetic.
 *
 * An operand takes part as an integer, a decimal or a double: a temporal value with fractional
 * seconds is a decimal and one without is an integer, a string is a double. Integer arithmetic
 * yields a BIGINT as wide as its operands allow; when an operand is a decimal or the operator
 * divides, the result is a DECIMAL whose precision and scale follow from the operands, a
 * division adding div_precision_increment digits to the scale of its dividend; any double makes
 * the result a DOUBLE. An integer product has the digits of both operands, at most 65.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/arithmetic-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/precision-math-expressions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Numbers
{
    /**
     * @param int $divPrecisionIncrement The digits a division adds to the scale of its dividend
     * @param bool $unsignedSubtraction Whether a subtraction with an unsigned operand is unsigned
     * @param bool $legacyFloating Whether nondivision fixed-scale real arithmetic uses 17 plus its scale, also in MySQL 8.0
     * @param bool $legacyRemainders Whether a remainder and an integer division count a temporal operand by its length, as MySQL 5.6 and 5.7 do
     */
    public function __construct(public readonly int $divPrecisionIncrement = 4, public readonly bool $unsignedSubtraction = true, public readonly bool $legacyRemainders = false, public readonly bool $legacyFloating = false)
    {
    }

    /**
     * Answers how an operand takes part in arithmetic: as an integer, a decimal or a double.
     */
    public function operand(Domain $domain): Kind
    {
        if ($domain->field === Field::VarString && ($domain->kind->temporal() || ($domain->kind === Kind::Year && !$domain->collation->bytes()))) {
            return Kind::Double;
        }
        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => Kind::Integer,
            Kind::Decimal => Kind::Decimal,
            Kind::Date, Kind::Time, Kind::DateTime => $domain->decimals > 0 ? Kind::Decimal : Kind::Integer,
            Kind::Double, Kind::String, Kind::Json, Kind::Null => Kind::Double,
        };
    }

    /**
     * Answers the precision and scale an operand brings to decimal arithmetic.
     *
     * @param bool $reported Whether integer results reported with fractional metadata contribute that precision
     * @return array{int, int}
     */
    public function digits(Domain $domain, bool $reported = true): array
    {
        if ($reported && $domain->displayDecimals !== null && in_array($domain->kind, [Kind::Year, Kind::Integer], true)) {
            return [max(1, $domain->length - ($domain->unsigned ? 0 : 1) - 1), $domain->displayDecimals];
        }

        return match ($domain->kind) {
            Kind::Decimal => [$domain->precision(), $domain->decimals],
            Kind::Date => [8, 0],
            Kind::Time => [7 + $domain->decimals, $domain->decimals],
            Kind::DateTime => [14 + $domain->decimals, $domain->decimals],
            Kind::Integer, Kind::Double, Kind::String, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => [max(1, $domain->length - ($domain->unsigned ? 0 : 1)), 0],
        };
    }

    /**
     * Resolves the result of an arithmetic operator other than a bit operator.
     */
    public function binary(ArithmeticOperator $operator, Domain $left, Domain $right): Domain
    {
        if ($operator === ArithmeticOperator::IntegerDivide) {
            return $this->quotient($left, $right);
        }
        $kinds = [$this->operand($left), $this->operand($right)];
        if (in_array(Kind::Double, $kinds, true)) {
            return $this->floating($operator, $left, $right);
        }
        if ($operator === ArithmeticOperator::Divide || in_array(Kind::Decimal, $kinds, true)) {
            return $this->decimal($operator, $this->digits($left), $this->digits($right));
        }
        return $this->integerResult($operator, $left, $right);
    }

    /**
     * Answers whether integer arithmetic has an unsigned result under the session subtraction mode.
     */
    public function unsigned(ArithmeticOperator $operator, Domain $left, Domain $right): bool
    {
        return ($left->unsigned || $right->unsigned) && ($operator !== ArithmeticOperator::Minus || $this->unsignedSubtraction);
    }

    /**
     * Resolves integer arithmetic, including reported fractional metadata carried by MySQL 8.0 YEAR results.
     */
    public function integerResult(ArithmeticOperator $operator, Domain $left, Domain $right): Domain
    {
        $unsigned = $this->unsigned($operator, $left, $right);
        $length = match ($operator) {
            ArithmeticOperator::Multiply => min(65, $this->digits($left, false)[0] + $this->digits($right, false)[0]) + ($unsigned ? 0 : 1),
            ArithmeticOperator::Modulo => ($this->legacyRemainders ? max($left->length, $right->length) : max($this->width($left), $this->width($right))) + ($unsigned && !$this->legacyRemainders ? 1 : 0),
            ArithmeticOperator::Plus, ArithmeticOperator::Minus, ArithmeticOperator::BitOr, ArithmeticOperator::BitAnd, ArithmeticOperator::BitXor,
            ArithmeticOperator::ShiftLeft, ArithmeticOperator::ShiftRight, ArithmeticOperator::Divide, ArithmeticOperator::IntegerDivide => max($this->width($left, $unsigned), $this->width($right, $unsigned)) + 1,
        };

        return $this->reportedInteger($operator, $left, $right, $length, $unsigned);
    }

    /**
     * Retains reported decimals separately from integer evaluation and applies the result width limit.
     */
    public function reportedInteger(ArithmeticOperator $operator, Domain $left, Domain $right, int $length, bool $unsigned): Domain
    {
        $scale = max($left->displayDecimals ?? 0, $right->displayDecimals ?? 0);
        $addition = in_array($operator, [ArithmeticOperator::Plus, ArithmeticOperator::Minus], true);
        if ($scale > 0) {
            $length = $addition ? max($left->length - ($left->displayDecimals ?? 0), $right->length - ($right->displayDecimals ?? 0)) + $scale + 1 : $length;
            $scale = $operator === ArithmeticOperator::Multiply ? min(30, $scale) : $scale;
        }
        $maximum = $this->legacyRemainders && $addition && !$unsigned ? 67 : 66;

        return new Domain(Kind::Integer, Field::LongLong, min($maximum, $length), 0, $unsigned, null, [], Coercibility::Numeric, displayDecimals: $scale > 0 ? $scale : null);
    }

    /**
     * Resolves floating arithmetic. Fixed-scale operands retain their greatest
     * scale; division adds div_precision_increment. Legacy nondivision results
     * use 17 plus that scale, while later releases retain the widest integral
     * operand. Textually reported temporal results use their reported decimals
     * without changing how their values convert to numbers. Verified through SQL
     * with FLOAT, DOUBLE and NULLIF on MySQL 5.6 and 8.4.
     */
    public function floating(ArithmeticOperator $operator, Domain $left, Domain $right): Domain
    {
        if ($left->kind === Kind::Null || $right->kind === Kind::Null) {
            return Domain::double(2, 0);
        }
        $fraction = static fn (Domain $domain): int => $domain->displayDecimals ?? ($domain->kind === Kind::Json || ($domain->kind === Kind::String && ($domain->coercibility !== Coercibility::Ignorable || $domain->length !== 0)) ? Domain::NOT_FIXED : $domain->decimals);
        $leftScale = $fraction($left);
        $rightScale = $fraction($right);
        $scale = max($leftScale, $rightScale);
        if ($scale >= Domain::NOT_FIXED) {
            return Domain::double(23);
        }
        $integral = max($left->length - $leftScale, $right->length - $rightScale);
        if ($operator === ArithmeticOperator::Divide) {
            $scale = min(Domain::NOT_FIXED, $scale + $this->divPrecisionIncrement);

            return Domain::double($integral + $scale, $scale);
        }

        return Domain::double(($this->legacyRemainders || $this->legacyFloating ? 17 : $integral) + $scale, $scale);
    }

    /**
     * Resolves an integer division (DIV): a BIGINT as long as its dividend written without its fraction.
     *
     * A temporal dividend counts its width, unless remainders count it by its length as MySQL 5.6
     * and 5.7 do. A divisor that is not an integer adds one character; the result is unsigned
     * when either operand is. The result width is bounded by 22 characters (21
     * unsigned) from MySQL 8.0; a floating-point operand takes that full bound.
     * MySQL 5.6 and 5.7 retain the dividend width, add nothing for the divisor,
     * and cap every result at 21. Verified through PDO and Testcontainers.
     */
    public function quotient(Domain $left, Domain $right): Domain
    {
        $fraction = $left->displayDecimals !== null && $this->operand($left) === Kind::Integer ? 1 : ($left->decimals > 0 && $left->decimals < Domain::NOT_FIXED ? $left->decimals + 1 : 0);
        $length = $this->legacyRemainders || !$left->kind->temporal() ? $left->length - $fraction : $this->width($left) - $left->decimals;
        $unsigned = $left->unsigned || $right->unsigned;
        $bound = $this->legacyRemainders || $unsigned ? 21 : 22;
        if (!$this->legacyRemainders && ($left->kind === Kind::Double || $right->kind === Kind::Double)) {
            return Domain::integer(Field::LongLong, $bound, $unsigned);
        }

        return Domain::integer(Field::LongLong, min($bound, max(1, $length + ($left->kind === Kind::String && !$this->legacyRemainders ? 1 : 0) + (!$this->legacyRemainders && $this->operand($right) !== Kind::Integer ? 1 : 0))), $unsigned);
    }

    /**
     * Answers the characters an operand takes in integer arithmetic: a temporal value its digits and a sign, 9 for a DATE, 15 for a DATETIME and 8 for a TIME; any other its length (verified on live 5.6, 5.7, 8.0, 8.4 and 9.1 servers).
     */
    public function width(Domain $domain, bool $unsigned = false): int
    {
        $length = $domain->kind->temporal() ? $this->digits($domain)[0] + 1 : $domain->length;

        return $length - ($unsigned && !$domain->unsigned ? 1 : 0);
    }

    /**
     * Resolves decimal arithmetic from the precision and scale of each operand.
     * Addition and subtraction reserve one carry digit in result metadata, up to 66 digits;
     * multiplication and division retain the 65-digit limit. Observed through result column
     * metadata on MySQL 5.6.51, 8.0.44, 8.4.7 and 9.1.0, independently of declaration limits.
     *
     * @param array{int, int} $left
     * @param array{int, int} $right
     */
    public function decimal(ArithmeticOperator $operator, array $left, array $right): Domain
    {
        [$leftPrecision, $leftScale] = $left;
        [$rightPrecision, $rightScale] = $right;
        $scale = max($leftScale, $rightScale);
        $integral = max($leftPrecision - $leftScale, $rightPrecision - $rightScale);
        [$precision, $scale] = match ($operator) {
            ArithmeticOperator::Multiply => [$leftPrecision + $rightPrecision, min(30, $leftScale + $rightScale)],
            ArithmeticOperator::Divide => [$leftPrecision + $rightScale + $this->divPrecisionIncrement, min(30, $leftScale + $this->divPrecisionIncrement)],
            ArithmeticOperator::Modulo => [$integral + $scale, $scale],
            ArithmeticOperator::Plus, ArithmeticOperator::Minus, ArithmeticOperator::IntegerDivide,
            ArithmeticOperator::BitOr, ArithmeticOperator::BitAnd, ArithmeticOperator::BitXor, ArithmeticOperator::ShiftLeft, ArithmeticOperator::ShiftRight => [$integral + 1 + $scale, $scale],
        };

        $maximum = in_array($operator, [ArithmeticOperator::Plus, ArithmeticOperator::Minus], true) ? 66 : 65;

        return Domain::decimal(min($maximum, $precision), $scale);
    }

    /**
     * Resolves the negation of an operand: a DECIMAL of its digits for an integer constant that is negative read as a signed BIGINT.
     *
     * @param bool $negative Whether the operand is an integer constant that is negative read as a signed BIGINT
     */
    public function negated(Domain $domain, bool $negative = false): Domain
    {
        if ($domain->kind->temporal()) {
            return Domain::double(17 + $domain->decimals, $domain->decimals);
        }

        return match ($this->operand($domain)) {
            Kind::Integer => $negative && $domain->kind === Kind::Integer ? Domain::decimal(...$this->digits($domain)) : new Domain(Kind::Integer, Field::LongLong, $domain->length + (int) $domain->unsigned, 0, false, null, [], Coercibility::Numeric, displayDecimals: $domain->displayDecimals),
            Kind::Decimal => $domain->kind === Kind::Decimal ? $domain : Domain::decimal(...$this->digits($domain)),
            Kind::Double, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => Domain::double(23, $domain->kind === Kind::Double ? $domain->decimals : Domain::NOT_FIXED),
        };
    }

    /**
     * Widens the result of unary minus over an exact number as MySQL 5.6 and 5.7 do: one character more than the operand, for the sign (verified on live 5.6.51 and 5.7.44 servers).
     */
    public function legacyNegated(Domain $result, bool $signIncluded = false): Domain
    {
        if ($signIncluded || ($result->kind !== Kind::Integer && $result->kind !== Kind::Decimal)) {
            return $result;
        }

        return new Domain($result->kind, $result->field, $result->length + 1, $result->decimals, $result->unsigned, $result->collation, $result->members, $result->coercibility, $result->display);
    }

    /**
     * Answers the signed width of a legacy arithmetic operand. Integer literals
     * reserve their sign; CASE, IF and IFNULL retain the widest signed result branch.
     * Their own reported widths remain unchanged. Verified through differential
     * arithmetic and text conversions on MySQL 5.6.51 and 5.7.44.
     */
    public function signed(Scalar $node, Domain $domain, ?Facts $facts = null): Domain
    {
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }
        if ($domain->kind !== Kind::Integer || $domain->unsigned) {
            return $domain;
        }
        if ($node instanceof NumberLiteral) {
            return Domain::integer($domain->field, $domain->length + 1);
        }
        if ($facts === null) {
            return $domain;
        }
        $branches = match (true) {
            $node instanceof CaseExpression => [...array_map(static fn ($branch): Scalar => $branch->result, $node->branches), ...($node->else === null ? [] : [$node->else])],
            $node instanceof KeywordCall && $node->function === KeywordFunction::If => array_slice($node->arguments, 1),
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall && $node->schema === null && strtoupper($node->name->value) === 'IFNULL' => array_map(static fn ($argument): Scalar => $argument->expression, $node->arguments),
            default => [],
        };
        $length = $domain->length;
        foreach ($branches as $branch) {
            $resolved = $facts->covers($branch) ? (new Precision())->domain($facts->scalar($branch)->type) : null;
            if ($resolved !== null) {
                $length = max($length, $this->signed($branch, $resolved->value(), $facts)->length);
            }
        }

        return $length === $domain->length ? $domain : Domain::integer($domain->field, $length);
    }

    /**
     * Resolves a bit operator on numbers: an unsigned BIGINT.
     */
    public function bits(): Domain
    {
        return Domain::integer(Field::LongLong, 21, true);
    }

    /**
     * Answers how an operand takes part in arithmetic: a hexadecimal or bit literal without an introducer as the unsigned integer its bytes spell.
     *
     * The integer is as wide as the largest value its bytes hold, and a bit literal one digit wider.
     */
    public function numeric(Scalar $node, Domain $domain): Domain
    {
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }
        if (!$node instanceof RadixLiteral || $node->introducer !== null || $domain->kind !== Kind::String) {
            return $domain;
        }
        $digits = strlen(rtrim(sprintf('%.0F', 256 ** $domain->length - 1), '.'));

        return Domain::integer(Field::LongLong, min(20, $digits) + ($node->radix === Radix::Bit ? 1 : 0), true);
    }

    /**
     * Resolves a bit operator on binary strings: a binary string as long as its longest operand, or its left operand for a shift.
     *
     * @param ArithmeticOperator|null $operator The binary operator, or null for the unary inversion
     * @param Domain $left The left operand, or the only one of the inversion
     * @param Domain|null $right The right operand of a binary operator
     */
    public function binaryBits(?ArithmeticOperator $operator, Domain $left, ?Domain $right = null): Domain
    {
        $shift = $operator === ArithmeticOperator::ShiftLeft || $operator === ArithmeticOperator::ShiftRight;
        $length = $shift || $right === null ? $left->length : max($left->length, $right->length);

        return Domain::string($length, Collation::binary());
    }

    /**
     * Resolves a truth value: comparisons, predicates and logical operators.
     */
    public function truth(): Domain
    {
        return Domain::integer(Field::LongLong, 1);
    }
}
