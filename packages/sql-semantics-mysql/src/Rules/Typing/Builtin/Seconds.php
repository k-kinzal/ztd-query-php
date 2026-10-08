<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Answers the fractional digits of seconds an argument of a date or time function carries, which the result keeps.
 *
 * A DATE, an integer, a YEAR and NULL carry none; a DATETIME, TIMESTAMP or TIME its own; a
 * DECIMAL its scale, at most six; a DOUBLE and any other string six. A string literal is read as
 * the server reads it, a date and time or a time: it carries the fractional digits it writes,
 * at most six, or six when it is no such value (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/fractional-seconds.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class Seconds
{
    /**
     * Answers the fractional digits of an argument.
     *
     * @param bool $time Whether a string is read as a time rather than a date and time
     */
    public function digits(Invocation $call, int $index, bool $time): int
    {
        $domain = $call->domain($index);

        return match ($domain->kind) {
            Kind::Null, Kind::Integer, Kind::Year, Kind::Bit, Kind::Date => 0,
            Kind::Decimal => min(6, $domain->decimals),
            Kind::DateTime, Kind::Time => min(6, $domain->decimals),
            Kind::Double => $domain->decimals < Domain::NOT_FIXED ? min(6, $domain->decimals) : 6,
            Kind::String, Kind::Json => $this->literal($call, $index, $time),
        };
    }

    /**
     * Answers the fractional digits a string argument carries: those a literal writes, else six.
     */
    public function literal(Invocation $call, int $index, bool $time): int
    {
        $node = $call->nodes[$index] ?? null;
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }
        if (!$node instanceof StringLiteral) {
            return 6;
        }

        return $this->written($node->value(), $time) ?? 6;
    }

    /**
     * Answers the fractional digits a text writes when read as a date and time or a time, or null when it is no such value.
     *
     * A date and time is read as up to six numbers between any punctuation, or as the digits
     * YYYYMMDDhhmmss; only a point after the seconds starts the fraction. A time is read as
     * `[-][D ]h[:m[:s]][.f]`, as digits hhmmss with a fraction, or as a date and time whose time
     * follows whitespace.
     */
    public function written(string $text, bool $time): ?int
    {
        $text = ltrim($text, " \t\n\r");
        if (preg_match('/\A([0-9]{1,4}[^0-9]+[0-9]{1,2}[^0-9]+[0-9]{1,2}' . ($time ? '\s+' : '[^0-9]+') . '[0-9]{1,2}[^0-9]+[0-9]{1,2}[^0-9]+[0-9]{1,2})\.([0-9]*)/', $text, $match) === 1) {
            return $this->valid($match[1]) ? min(6, strlen($match[2])) : null;
        }
        if ($time) {
            if (preg_match('/\A-?(?:[0-9]+ +)?[0-9]+(?::([0-9]{1,2})(?::([0-9]{1,2}))?)?(?:\.([0-9]*))?/', $text, $match) === 1 || preg_match('/\A-?()()\.([0-9]+)/', $text, $match) === 1) {
                return (int) ($match[1] ?? 0) > 59 || (int) ($match[2] ?? 0) > 59 ? null : min(6, strlen($match[3] ?? ''));
            }

            return null;
        }
        if (preg_match('/\A(?:[0-9]{12}|[0-9]{14})\.([0-9]*)/', $text, $match) === 1) {
            return min(6, strlen($match[1]));
        }
        if (preg_match('/\A[0-9]{1,4}[^0-9]+[0-9]{1,2}[^0-9]+[0-9]{1,2}(?:[^0-9]+[0-9]{1,2}(?:[^0-9]+[0-9]{1,2}(?:[^0-9]+[0-9]{1,2})?)?)?/', $text, $match) === 1) {
            return $this->valid($match[0]) ? 0 : null;
        }

        return preg_match('/\A[0-9]{6,}/', $text) === 1 ? 0 : null;
    }

    /**
     * Tells whether the numbers of a date and time name a month, day, hour, minute and second within their ranges.
     */
    public function valid(string $text): bool
    {
        $pieces = preg_split('/[^0-9]+/', $text);
        $numbers = array_map('intval', $pieces === false ? [] : $pieces);

        $month = $numbers[1] ?? 0;
        $day = $numbers[2] ?? 0;
        $exists = $month === 0 || $day === 0 || ($month <= 12 && checkdate($month, $day, max(1, $numbers[0] ?? 0)));

        return $exists && $month <= 12 && $day <= 31 && ($numbers[3] ?? 0) <= 23 && ($numbers[4] ?? 0) <= 59 && ($numbers[5] ?? 0) <= 59;
    }
}
