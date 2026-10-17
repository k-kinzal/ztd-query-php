<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Math;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Moments;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Ordering;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Encoding;
use MySqlMemory\Value\Integer;
use MySqlMemory\Value\Order;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * GREATEST and LEAST: the largest or smallest argument, NULL when any argument is NULL.
 *
 * The arguments compare in the kind of the result: as integers, decimals or doubles when it is a
 * number, as temporal values when it is one, and as strings in its collation when it is a
 * string. When a date or datetime is among arguments of other kinds, they compare as datetimes,
 * or as dates when no argument is a datetime: a time is taken on the current day, and a value
 * that is no date warns that it is an incorrect date or datetime value for the first argument of
 * that kind, at the row of the call. GREATEST passes over such a value; LEAST compares it with
 * the others as their texts, and then answers the text of the winner. Otherwise the result
 * writes the winner as a date or a datetime with the fractional digits of the result. Of equal arguments GREATEST answers the last and LEAST the first. A JSON argument warns
 * once, when the statement is resolved, that JSON values are compared as strings
 * (ER_NOT_SUPPORTED_YET).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html#function_greatest;
 * the comparisons are verified on a live 8.4 server.
 *
 * @visibility MySqlMemory
 */
final class Extremes
{
    /**
     * The feature the warning of a JSON argument names.
     */
    public const JSON = 'comparison of JSON in the LEAST and GREATEST operators';

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('GREATEST', 2, -1, fn (Frame $f, array $a, Domain $r, string $t): int|float|string|null => $this->extreme($f, $a, $r, $t, true), 1, $this->resolve(...)),
            new Routine('LEAST', 2, -1, fn (Frame $f, array $a, Domain $r, string $t): int|float|string|null => $this->extreme($f, $a, $r, $t, false), 1, $this->resolve(...)),
        ];
    }

    /**
     * Warns once of a JSON argument, and marks the call for counting its rows.
     *
     * @param list<Evaluable> $arguments
     * @param list<bool> $known
     * @return list<Evaluable>
     */
    public function resolve(Frame $frame, array $arguments, array $known): array
    {
        if (array_filter($arguments, static fn (Evaluable $argument): bool => $argument->domain()->kind === Kind::Json) !== []) {
            $frame->context->warning(StatementError::NotSupportedYet, self::JSON);
        }
        $arguments[0] = new Tally($arguments[0]);

        return $arguments;
    }

    /**
     * Answers the largest or smallest argument, in the type of the result.
     *
     * @param list<Evaluable> $arguments
     * @param string $text The call as the server prints it
     * @param bool $greatest Whether the largest argument is asked for
     */
    public function extreme(Frame $frame, array $arguments, Domain $result, string $text, bool $greatest): int|float|string|null
    {
        $dated = array_filter($arguments, static fn (Evaluable $argument): bool => $argument->domain()->kind === Kind::Date || $argument->domain()->kind === Kind::DateTime);
        $mode = match (true) {
            $result->kind->temporal() => $result->kind,
            $dated !== [] && ($result->kind === Kind::String || $result->kind === Kind::Json) => $this->dating($arguments),
            default => $result->kind,
        };
        $best = null;
        $winner = $arguments[0];
        $textual = false;
        foreach ($arguments as $argument) {
            $value = $argument->evaluate($frame);
            if ($value === null) {
                return null;
            }
            $rank = $this->rank($value, $argument->domain(), $mode, $result, $frame, $arguments, $text);
            if ($best === null) {
                [$best, $winner] = [$rank, $argument];
                continue;
            }
            if ($greatest) {
                $taken = $best->dateless() ? !$rank->dateless() : !$rank->dateless() && $this->compare($rank, $best, $mode, $result) >= 0;
            } elseif ($rank->dateless() || $best->dateless()) {
                $textual = true;
                $taken = Ordering::of($result->collation)->compare((string) $rank->value, (string) $best->value) < 0;
            } else {
                $taken = $this->compare($rank, $best, $mode, $result) < 0;
            }
            if ($taken) {
                [$best, $winner] = [$rank, $argument];
            }
        }
        if ($best === null) {
            return null;
        }

        return $this->written($textual ? new Rank($best->order, $best->value, false, null) : $best, $winner, $mode, $result, $arguments);
    }

    /**
     * Answers how arguments of mixed kinds with a date compare: as datetimes when a datetime is among them, else as dates.
     *
     * @param list<Evaluable> $arguments
     */
    public function dating(array $arguments): Kind
    {
        foreach ($arguments as $argument) {
            if ($argument->domain()->kind === Kind::DateTime) {
                return Kind::DateTime;
            }
        }

        return Kind::Date;
    }

    /**
     * Reads an argument as it compares in a kind.
     *
     * @param list<Evaluable> $arguments
     */
    public function rank(int|float|string $value, Domain $domain, Kind $mode, Domain $result, Frame $frame, array $arguments, string $text): Rank
    {
        $context = $frame->context;
        $unsigned = $domain->unsigned || $domain->kind === Kind::Bit;

        return match ($mode) {
            Kind::Integer, Kind::Year, Kind::Bit => new Rank((int) Convert::toInteger($value, $domain, $context, $unsigned), $value, $unsigned),
            Kind::Decimal => new Rank((string) Convert::toDecimal($value, $domain, $context), $value),
            Kind::Double => new Rank((float) Convert::toDouble($value, $domain, $context), $value),
            Kind::String, Kind::Json, Kind::Null => new Rank(Comparator::text($value, $domain, $result->collation), $value),
            Kind::Time => new Rank(Order::time((string) $value), $value),
            Kind::Date, Kind::DateTime => $this->moment($value, $domain, $mode, $frame, $arguments, $text),
        };
    }

    /**
     * Reads an argument as a date or datetime; a value that is no date warns.
     *
     * @param list<Evaluable> $arguments
     */
    public function moment(int|float|string $value, Domain $domain, Kind $mode, Frame $frame, array $arguments, string $text): Rank
    {
        $context = $frame->context;
        $shown = (string) Convert::toText($value, $domain);
        $parts = null;
        if ($domain->kind === Kind::Date || $domain->kind === Kind::DateTime) {
            $read = Temporal::parseDateTime((string) $value);
            $parts = $read === null ? null : [$read[0], $read[1], $read[2], $read[3], $read[4], $read[5], $read[6]];
        } elseif ($domain->kind === Kind::Time) {
            $time = Temporal::scanTime((string) $value);
            $moment = $time === null ? null : Temporal::onDay($context->zone()->local((int) floor($context->started)), $time[0], $time[1], $time[2], $time[3], Temporal::micro($time[4], false));
            $parts = $moment === null ? null : [$moment[0], $moment[1], $moment[2], $moment[3], $moment[4], $moment[5], $moment[6]];
        } else {
            $scanned = Temporal::scanDateTime($domain->kind === Kind::String || $domain->kind === Kind::Json ? $shown : (new Moments())->digits($value, $domain, $context));
            $modes = $context->modes;
            $rest = '';
            if ($scanned !== null && Temporal::accepted($scanned[0], $scanned[1], $scanned[2], $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE')) && $scanned[3] <= 23 && $scanned[4] <= 59 && $scanned[5] <= 59) {
                $parts = [$scanned[0], $scanned[1], $scanned[2], $scanned[3], $scanned[4], $scanned[5], Temporal::micro($scanned[6], false)];
                $rest = $scanned[8];
            }
            if ($parts === null || $rest !== '') {
                $this->warn($frame, $mode, Convert::shown($shown, Convert::readableCharset($domain)), $arguments, $text);
            }
        }
        if ($parts === null) {
            return new Rank('', $shown, false, null);
        }
        if ($mode === Kind::Date) {
            $parts = [$parts[0], $parts[1], $parts[2], 0, 0, 0, 0];
        }

        return new Rank(vsprintf('%04d%02d%02d%02d%02d%02d%06d', $parts), $shown, false, $parts);
    }

    /**
     * Warns that a value is no date or datetime for the argument that decides how the arguments compare, at the row of the call.
     *
     * @param list<Evaluable> $arguments
     */
    public function warn(Frame $frame, Kind $mode, string $shown, array $arguments, string $text): void
    {
        $names = $this->names($text);
        $column = '';
        foreach ($arguments as $index => $argument) {
            if ($argument->domain()->kind === $mode) {
                $column = $names[$index] ?? '';
                if (preg_match('/\A(?:`(?:[^`]|``)*`\.)*`((?:[^`]|``)*)`\z/', $column, $name) === 1) {
                    $column = str_replace('``', '`', $name[1]);
                }
                break;
            }
        }
        $row = 1;
        $tally = $arguments[0];
        if ($tally instanceof Tally) {
            $row = $tally->row($frame);
        }
        $frame->context->warnMessage(DataError::TruncatedWrongValue, DataError::TruncatedWrongValueForField->message($mode === Kind::Date ? 'date' : 'datetime', $shown, $column, $row));
    }

    /**
     * Splits the arguments out of the text of a call.
     *
     * @return list<string>
     */
    public function names(string $text): array
    {
        $open = strpos($text, '(');
        $body = $open === false ? '' : substr($text, $open + 1, -1);
        $names = [];
        $depth = 0;
        $quote = null;
        $current = '';
        $length = strlen($body);
        for ($at = 0; $at < $length; $at++) {
            $character = $body[$at];
            if ($quote !== null) {
                $current .= $character;
                $quote = $character === $quote ? null : $quote;
                continue;
            }
            if ($character === ',' && $depth === 0) {
                $names[] = trim($current);
                $current = '';
                continue;
            }
            $depth += match ($character) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };
            $quote = in_array($character, ["'", '"', '`'], true) ? $character : null;
            $current .= $character;
        }
        $names[] = trim($current);

        return $names;
    }

    /**
     * Compares two arguments as they rank in a kind.
     */
    public function compare(Rank $left, Rank $right, Kind $mode, Domain $result): int
    {
        return match ($mode) {
            Kind::Integer, Kind::Year, Kind::Bit => Integer::compare((int) $left->order, $left->unsigned, (int) $right->order, $right->unsigned),
            Kind::Decimal => Decimal::compare((string) $left->order, (string) $right->order),
            Kind::Double, Kind::Time => (float) $left->order <=> (float) $right->order,
            Kind::String, Kind::Json, Kind::Null => Ordering::of($result->collation)->compare((string) $left->order, (string) $right->order),
            Kind::Date, Kind::DateTime => strcmp((string) $left->order, (string) $right->order),
        };
    }

    /**
     * Writes the winning argument in the type of the result: a value that is no date as its own text.
     *
     * @param list<Evaluable> $arguments
     */
    public function written(Rank $best, Evaluable $winner, Kind $mode, Domain $result, array $arguments): int|float|string
    {
        $parts = $best->parts;
        return match ($mode) {
            Kind::Integer, Kind::Year, Kind::Bit => $result->kind === Kind::Bit ? (string) $best->order : (int) $best->order,
            Kind::Decimal => Decimal::round((string) $best->order, $result->decimals),
            Kind::Double => (float) $best->order,
            Kind::String, Kind::Json, Kind::Null => (string) $best->order,
            Kind::Time => $this->time((string) $best->value, $result->decimals),
            Kind::Date, Kind::DateTime => match (true) {
                $parts === null => Encoding::convert((string) $best->value, $winner->domain()->kind === Kind::String ? $winner->domain()->collation->charset : Charset::known('utf8mb4'), $result->collation->charset),
                count($parts) < 7 || $mode === Kind::Date || $result->kind === Kind::Date => Temporal::date($parts[0] ?? 0, $parts[1] ?? 0, $parts[2] ?? 0),
                default => Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6], $result->kind === Kind::String || $result->kind === Kind::Json ? $this->fraction($arguments) : $result->decimals),
            },
        };
    }

    /**
     * Writes a time with a number of fractional digits.
     */
    public function time(string $value, int $decimals): string
    {
        $time = Temporal::scanTime($value);

        return $time === null ? $value : Temporal::time($time[0], $time[1], $time[2], $time[3], Temporal::micro($time[4], false), $decimals);
    }

    /**
     * Answers the fractional digits a datetime written as a string keeps: those of the argument with the most, at most six, a string or one without fixed decimals counting six.
     *
     * @param list<Evaluable> $arguments
     */
    public function fraction(array $arguments): int
    {
        $digits = 0;
        foreach ($arguments as $argument) {
            $domain = $argument->domain();
            $digits = max($digits, match (true) {
                $domain->kind === Kind::Null => 0,
                $domain->kind === Kind::String || $domain->kind === Kind::Json || $domain->decimals >= 31 => 6,
                default => $domain->decimals,
            });
        }

        return min(6, $digits);
    }
}
