<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Integer;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The numeric functions: ABS, SIGN, CEILING, FLOOR, ROUND, TRUNCATE and the functions of real numbers.
 *
 * ABS, CEILING, FLOOR, ROUND and TRUNCATE keep an exact argument exact; the functions of real
 * numbers compute in double precision and are NULL outside their domain.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/mathematical-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Numbers
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        $routines = [
            new Routine('ABS', 1, 1, $this->abs(...)),
            new Routine('SIGN', 1, 1, $this->sign(...)),
            new Routine('CEILING', 1, 1, fn (Frame $f, array $a, Domain $r): int|float|string|null => $this->toward($f, $a, $r, true)),
            new Routine('CEIL', 1, 1, fn (Frame $f, array $a, Domain $r): int|float|string|null => $this->toward($f, $a, $r, true)),
            new Routine('FLOOR', 1, 1, fn (Frame $f, array $a, Domain $r): int|float|string|null => $this->toward($f, $a, $r, false)),
            new Routine('ROUND', 1, 2, fn (Frame $f, array $a, Domain $r): int|float|string|null => $this->round($f, $a, $r, false)),
            new Routine('TRUNCATE', 2, 2, fn (Frame $f, array $a, Domain $r): int|float|string|null => $this->round($f, $a, $r, true)),
            new Routine('PI', 0, 0, fn (): float => M_PI),
        ];
        $functions = [
            'SQRT' => static fn (float $x): ?float => $x < 0 ? null : sqrt($x),
            'EXP' => static fn (float $x): float => exp($x),
            'LN' => static fn (float $x): ?float => $x <= 0 ? null : log($x),
            'LOG2' => static fn (float $x): ?float => $x <= 0 ? null : log($x, 2),
            'LOG10' => static fn (float $x): ?float => $x <= 0 ? null : log10($x),
            'SIN' => static fn (float $x): float => sin($x),
            'COS' => static fn (float $x): float => cos($x),
            'TAN' => static fn (float $x): float => tan($x),
            'ASIN' => static fn (float $x): ?float => $x < -1 || $x > 1 ? null : asin($x),
            'ACOS' => static fn (float $x): ?float => $x < -1 || $x > 1 ? null : acos($x),
            'ATAN' => static fn (float $x): float => atan($x),
            'COT' => static fn (float $x): float => 1 / tan($x),
            'DEGREES' => static fn (float $x): float => rad2deg($x),
            'RADIANS' => static fn (float $x): float => deg2rad($x),
        ];
        foreach ($functions as $name => $function) {
            $routines[] = new Routine($name, 1, 1, fn (Frame $f, array $a, Domain $r): ?float => $this->real($f, $a, $function));
        }
        $routines[] = new Routine('POW', 2, 2, fn (Frame $f, array $a, Domain $r): ?float => $this->power($f, $a));
        $routines[] = new Routine('POWER', 2, 2, fn (Frame $f, array $a, Domain $r): ?float => $this->power($f, $a));

        return $routines;
    }




    /**
     * ABS: the absolute value.
     *
     * @param list<Evaluable> $arguments
     */
    public function abs(Frame $frame, array $arguments, Domain $result): int|float|string|null
    {
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $domain = $arguments[0]->domain();

        return match ($result->kind) {
            Kind::Integer => $result->unsigned ? (int) Convert::toInteger($value, $domain, $frame->context, true) : $this->absolute((int) Convert::toInteger($value, $domain, $frame->context)),
            Kind::Decimal => ltrim((string) Convert::toDecimal($value, $domain, $frame->context), '-'),
            Kind::Double, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => abs((float) Convert::toDouble($value, $domain, $frame->context)),
        };
    }

    /**
     * Answers the absolute value of an int, refusing the smallest BIGINT.
     */
    public function absolute(int $value): int
    {
        if ($value === PHP_INT_MIN) {
            throw ErrorCode::DataOutOfRange->error('BIGINT', 'abs(' . $value . ')');
        }

        return abs($value);
    }

    /**
     * SIGN: -1, 0 or 1.
     *
     * @param list<Evaluable> $arguments
     */
    public function sign(Frame $frame, array $arguments, Domain $result): ?int
    {
        $value = Convert::toDecimal($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context);

        return $value === null ? null : Decimal::compare($value, '0');
    }

    /**
     * CEILING and FLOOR: the nearest integer above or below.
     *
     * @param list<Evaluable> $arguments
     */
    public function toward(Frame $frame, array $arguments, Domain $result, bool $up): int|float|string|null
    {
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $domain = $arguments[0]->domain();
        if ($result->kind === Kind::Double) {
            $number = (float) Convert::toDouble($value, $domain, $frame->context);

            return $up ? ceil($number) : floor($number);
        }
        $decimal = (string) Convert::toDecimal($value, $domain, $frame->context);
        $truncated = Decimal::truncate($decimal, 0);
        if (Decimal::compare($decimal, $truncated) !== 0 && (Decimal::compare($decimal, '0') > 0) === $up) {
            $truncated = Decimal::add($truncated, $up ? '1' : '-1');
        }

        return $result->kind === Kind::Integer ? ($result->unsigned ? Integer::fromUnsignedText($truncated) : (int) $truncated) : $truncated;
    }

    /**
     * ROUND and TRUNCATE: the value rounded half away from zero, or truncated, to a number of decimals.
     *
     * @param list<Evaluable> $arguments
     */
    public function round(Frame $frame, array $arguments, Domain $result, bool $truncate): int|float|string|null
    {
        $value = $arguments[0]->evaluate($frame);
        $places = isset($arguments[1]) ? Convert::toInteger($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context) : 0;
        if ($value === null || $places === null) {
            return null;
        }
        $domain = $arguments[0]->domain();
        $places = max(-65, min(30, $places));
        if ($result->kind === Kind::Double) {
            $number = (float) Convert::toDouble($value, $domain, $frame->context);
            $factor = 10 ** $places;

            return $truncate ? ($number < 0 ? ceil($number * $factor) : floor($number * $factor)) / $factor : round($number * $factor, 0, PHP_ROUND_HALF_EVEN) / $factor;
        }
        $decimal = (string) Convert::toDecimal($value, $domain, $frame->context);
        $rounded = $truncate ? Decimal::truncate($decimal, $places) : Decimal::round($decimal, $places);
        if ($result->kind === Kind::Integer) {
            return $result->unsigned ? Integer::fromUnsignedText($rounded) : (int) $rounded;
        }

        return Decimal::round($rounded, $result->decimals);
    }

    /**
     * Applies a function of a real number, NULL outside its domain.
     *
     * @param list<Evaluable> $arguments
     * @param callable(float): ?float $function
     */
    public function real(Frame $frame, array $arguments, callable $function): ?float
    {
        $value = Convert::toDouble($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context);
        if ($value === null) {
            return null;
        }
        $result = $function($value);

        return $result === null || is_nan($result) ? null : $result;
    }

    /**
     * POW(x, y): x raised to the power y.
     *
     * @param list<Evaluable> $arguments
     */
    public function power(Frame $frame, array $arguments): ?float
    {
        $base = Convert::toDouble($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context);
        $exponent = Convert::toDouble($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context);
        if ($base === null || $exponent === null) {
            return null;
        }
        $result = $base ** $exponent;
        if (is_infinite($result) || is_nan($result)) {
            throw ErrorCode::DataOutOfRange->error('DOUBLE', 'pow(' . $base . ',' . $exponent . ')');
        }

        return $result;
    }
}
