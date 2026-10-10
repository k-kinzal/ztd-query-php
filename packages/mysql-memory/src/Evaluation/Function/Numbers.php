<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;
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
 * numbers compute in double precision, are NULL outside their domain, and refuse an infinite
 * result. DEGREES and RADIANS multiply by a factor and add zero, so a negative zero is zero.
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
            new Routine('ABS', 1, 1, fn (Frame $f, array $a, Domain $r, string $t): int|float|string|null => $this->abs($f, $a, $r, $t)),
            new Routine('SIGN', 1, 1, $this->sign(...)),
            new Routine('CEILING', 1, 1, fn (Frame $f, array $a, Domain $r): int|float|string|null => $this->toward($f, $a, $r, true)),
            new Routine('CEIL', 1, 1, fn (Frame $f, array $a, Domain $r): int|float|string|null => $this->toward($f, $a, $r, true)),
            new Routine('FLOOR', 1, 1, fn (Frame $f, array $a, Domain $r): int|float|string|null => $this->toward($f, $a, $r, false)),
            new Routine('ROUND', 1, 2, fn (Frame $f, array $a, Domain $r, string $t): int|float|string|null => $this->round($f, $a, $r, false, $t)),
            new Routine('TRUNCATE', 2, 2, fn (Frame $f, array $a, Domain $r, string $t): int|float|string|null => $this->round($f, $a, $r, true, $t)),
            new Routine('PI', 0, 0, fn (): float => M_PI),
        ];
        $functions = [
            'SQRT' => static fn (float $x): ?float => $x < 0 ? null : sqrt($x),
            'EXP' => static fn (float $x): float => exp($x),
            'SIN' => static fn (float $x): float => sin($x),
            'COS' => static fn (float $x): float => cos($x),
            'TAN' => static fn (float $x): float => tan($x),
            'ASIN' => static fn (float $x): ?float => $x < -1 || $x > 1 ? null : asin($x),
            'ACOS' => static fn (float $x): ?float => $x < -1 || $x > 1 ? null : acos($x),
            'COT' => static fn (float $x): float => fdiv(1, tan($x)),
            'DEGREES' => static fn (float $x): float => $x * (180 / M_PI) + 0.0,
            'RADIANS' => static fn (float $x): float => $x * (M_PI / 180) + 0.0,
        ];
        foreach ($functions as $name => $function) {
            $routines[] = new Routine($name, 1, 1, fn (Frame $f, array $a, Domain $r, string $t): ?float => $this->real($f, $a, $function, $t));
        }
        foreach (['LN' => null, 'LOG2' => 2.0, 'LOG10' => 10.0] as $name => $base) {
            $routines[] = new Routine($name, 1, 1, fn (Frame $f, array $a): ?float => $this->logarithm($f, $a, $base));
        }
        $routines[] = new Routine('LOG', 1, 2, fn (Frame $f, array $a): ?float => $this->logarithm($f, $a, null));
        $routines[] = new Routine('POW', 2, 2, fn (Frame $f, array $a, Domain $r, string $t): ?float => $this->power($f, $a, $t));
        $routines[] = new Routine('POWER', 2, 2, fn (Frame $f, array $a, Domain $r, string $t): ?float => $this->power($f, $a, $t));

        return $routines;
    }




    /**
     * ABS: the absolute value.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the argument is the smallest BIGINT
     */
    public function abs(Frame $frame, array $arguments, Domain $result, string $text = ''): int|float|string|null
    {
        $value = $arguments[0]->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $domain = $arguments[0]->domain();

        return match ($result->kind) {
            Kind::Integer => $result->unsigned ? (int) Convert::toInteger($value, $domain, $frame->context, true) : $this->absolute((int) Convert::toInteger($value, $domain, $frame->context), $text),
            Kind::Decimal => ltrim((string) Convert::toDecimal($value, $domain, $frame->context), '-'),
            Kind::Double, Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Year, Kind::Json, Kind::Bit, Kind::Null => abs((float) Convert::toDouble($value, $domain, $frame->context)),
        };
    }

    /**
     * Answers the absolute value of an int, refusing the smallest BIGINT.
     *
     * @param string $text The call as the server prints it in the message
     *
     * @throws SqlError When the value is the smallest BIGINT
     */
    public function absolute(int $value, string $text = ''): int
    {
        if ($value === PHP_INT_MIN) {
            throw DataError::DataOutOfRange->error('BIGINT', $text);
        }

        return abs($value);
    }

    /**
     * SIGN: -1, 0 or 1; an exact number is compared exactly, another value as a double.
     *
     * @param list<Evaluable> $arguments
     */
    public function sign(Frame $frame, array $arguments, Domain $result): ?int
    {
        $domain = $arguments[0]->domain();
        $value = $arguments[0]->evaluate($frame);
        if ($domain->kind === Kind::Integer || $domain->kind === Kind::Decimal || $domain->kind === Kind::Year || $domain->kind === Kind::Bit) {
            $exact = Convert::toDecimal($value, $domain, $frame->context);

            return $exact === null ? null : Decimal::compare($exact, '0');
        }
        $real = Convert::toDouble($value, $domain, $frame->context);

        return $real === null ? null : $real <=> 0.0;
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
     * An integer result outside the BIGINT range of its signedness is an error.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When an integer result is out of range
     */
    public function round(Frame $frame, array $arguments, Domain $result, bool $truncate, string $text): int|float|string|null
    {
        $value = $arguments[0]->evaluate($frame);
        $places = isset($arguments[1]) ? Convert::toInteger($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context) : 0;
        if ($value === null || $places === null) {
            return null;
        }
        $domain = $arguments[0]->domain();
        if ($result->kind === Kind::Double) {
            return $this->roundReal((float) Convert::toDouble($value, $domain, $frame->context), $places, $truncate);
        }
        $places = max(-65, min(30, $places));
        $decimal = (string) Convert::toDecimal($value, $domain, $frame->context);
        $rounded = $truncate ? Decimal::truncate($decimal, $places) : Decimal::round($decimal, $places);
        if ($result->kind === Kind::Integer) {
            if (!($result->unsigned ? Integer::unsignedRange($rounded) : Integer::signedRange($rounded))) {
                throw DataError::DataOutOfRange->error($result->unsigned ? 'BIGINT UNSIGNED' : 'BIGINT', $text);
            }

            return $result->unsigned ? Integer::fromUnsignedText($rounded) : (int) $rounded;
        }

        return Decimal::round($rounded, $result->decimals);
    }

    /**
     * Rounds half to even, or truncates, a double to a number of decimals.
     *
     * A scale too fine to apply leaves the number unchanged; a power of ten too large to hold
     * rounds it to zero. A rounding past the largest double answers an infinity.
     */
    public function roundReal(float $number, int $places, bool $truncate): float
    {
        $factor = 10.0 ** abs($places);
        if ($places >= 0) {
            $scaled = $number * $factor;
            if (!is_finite($scaled)) {
                return $number;
            }

            return ($truncate ? ($scaled < 0 ? ceil($scaled) : floor($scaled)) : round($scaled, 0, PHP_ROUND_HALF_EVEN)) / $factor;
        }
        if (is_infinite($factor)) {
            return 0.0;
        }
        $scaled = $number / $factor;

        return ($truncate ? ($scaled < 0 ? ceil($scaled) : floor($scaled)) : round($scaled, 0, PHP_ROUND_HALF_EVEN)) * $factor;
    }

    /**
     * Applies a function of a real number, NULL outside its domain.
     *
     * @param list<Evaluable> $arguments
     * @param callable(float): ?float $function
     *
     * @throws SqlError When the result is infinite
     */
    public function real(Frame $frame, array $arguments, callable $function, string $text): ?float
    {
        $value = Convert::toDouble($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context);
        if ($value === null) {
            return null;
        }
        $result = $function($value);
        if ($result !== null && is_infinite($result)) {
            throw DataError::DataOutOfRange->error('DOUBLE', $text);
        }

        return $result === null || is_nan($result) ? null : $result;
    }

    /**
     * LN, LOG, LOG2 and LOG10: a logarithm, NULL with a warning when an argument is outside its domain.
     *
     * The arguments are read in order: a NULL one makes the result NULL, and one that is not
     * positive makes it NULL with a warning, before the next is read. LOG(b, x) divides the
     * natural logarithms, and has no value for b = 1 either.
     *
     * @param list<Evaluable> $arguments The base and the number of LOG(b, x), else the number
     * @param float|null $base The base of a function of one argument, or null for the natural logarithm
     *
     * @throws SqlError When the statement raises warnings as errors
     */
    public function logarithm(Frame $frame, array $arguments, ?float $base): ?float
    {
        $values = [];
        foreach ($arguments as $argument) {
            $value = Convert::toDouble($argument->evaluate($frame), $argument->domain(), $frame->context);
            if ($value === null) {
                return null;
            }
            if ($value <= 0) {
                $frame->context->warning(DataError::InvalidLogarithmArgument);

                return null;
            }
            $values[] = $value;
        }
        if (count($values) !== 2) {
            $number = $values[0] ?? 1.0;

            return $base === null ? log($number) : ($base === 10.0 ? log10($number) : log($number, $base));
        }
        if ($values[0] === 1.0) {
            $frame->context->warning(DataError::InvalidLogarithmArgument);

            return null;
        }

        return log($values[1]) / log($values[0]);
    }

    /**
     * POW(x, y): x raised to the power y.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws SqlError When the result is out of range
     */
    public function power(Frame $frame, array $arguments, string $text): ?float
    {
        $base = Convert::toDouble($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context);
        $exponent = Convert::toDouble($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context);
        if ($base === null || $exponent === null) {
            return null;
        }
        $result = $base === 0.0 && $exponent < 0 ? INF : $base ** $exponent;
        if (is_infinite($result) || is_nan($result)) {
            throw DataError::DataOutOfRange->error('DOUBLE', $text);
        }

        return $result;
    }
}
