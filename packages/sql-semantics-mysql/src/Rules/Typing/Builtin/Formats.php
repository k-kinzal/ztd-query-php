<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Resolves the results of the functions that take a format string: DATE_FORMAT, TIME_FORMAT and FROM_UNIXTIME write one, STR_TO_DATE reads one.
 *
 * A format written as a string literal is read: each specifier counts as long as the longest
 * text it writes, every other byte as one character; an empty format writes nothing. Any other
 * format makes the result ten times as long as the format written as text. STR_TO_DATE gives a
 * DATE, a TIME or a DATETIME by the specifiers of a literal format, with microseconds for %f, and
 * a DATETIME(6) for a format that is no literal (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_date-format.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class Formats
{
    /**
     * The longest text each specifier writes, in characters.
     */
    public const LENGTHS = [
        'a' => 32, 'b' => 32, 'c' => 2, 'D' => 4, 'd' => 2, 'e' => 2, 'f' => 6, 'H' => 7, 'h' => 2, 'I' => 2, 'i' => 2, 'j' => 3,
        'k' => 7, 'l' => 2, 'M' => 64, 'm' => 2, 'p' => 2, 'r' => 11, 'S' => 2, 's' => 2, 'T' => 8, 'U' => 2, 'u' => 2, 'V' => 2,
        'v' => 2, 'W' => 64, 'w' => 1, 'X' => 4, 'x' => 4, 'Y' => 4, 'y' => 2,
    ];

    /**
     * The specifiers STR_TO_DATE reads as part of a date.
     */
    public const DATED = 'abcjMmUuVvWwXxYy';

    /**
     * The specifiers STR_TO_DATE reads as part of a time.
     */
    public const TIMED = 'fHhIiklrSsT';

    /**
     * Resolves a string written by a format argument: in the connection collation, as coercible as the format.
     */
    public function written(Invocation $call, int $format): Domain
    {
        $settings = $call->settings;
        $coercibility = $call->collations()->operand($call->domain($format))[1];
        $length = $this->length($call, $format);

        return Domain::string(min($length, 4294967295), $settings->connection, $length > 16383 ? Field::Blob : Field::VarString, $coercibility);
    }

    /**
     * Answers the longest text a format argument writes, in characters.
     */
    public function length(Invocation $call, int $format): int
    {
        $text = $this->literal($call, $format, false);
        if ($text === null) {
            return $call->length($call->domain($format)) * 10;
        }
        $length = 0;
        for ($index = 0, $size = strlen($text); $index < $size; $index++) {
            if ($text[$index] === '%' && $index + 1 < $size) {
                $length += self::LENGTHS[$text[++$index]] ?? 1;
                continue;
            }
            $length++;
        }

        return $length;
    }

    /**
     * Resolves STR_TO_DATE by its format argument.
     */
    public function parsed(Invocation $call, int $format): Domain
    {
        $text = $this->literal($call, $format, true);
        if ($text === null) {
            return new Domain(Kind::DateTime, Field::DateTime, 26, 6);
        }
        $dated = false;
        $timed = false;
        $micro = false;
        for ($index = 0, $size = strlen($text); $index + 1 < $size; $index++) {
            if ($text[$index] !== '%') {
                continue;
            }
            $specifier = $text[++$index];
            $dated = $dated || str_contains(self::DATED, $specifier);
            $timed = $timed || str_contains(self::TIMED, $specifier);
            $micro = $micro || $specifier === 'f';
        }
        $decimals = $micro ? 6 : 0;
        $fraction = $micro ? 7 : 0;

        return match (true) {
            $dated && $timed => new Domain(Kind::DateTime, Field::DateTime, 19 + $fraction, $decimals),
            $timed => new Domain(Kind::Time, Field::Time, ((new DateResults())->legacy($call) && $micro ? 23 : 10) + $fraction, $decimals),
            default => new Domain(Kind::Date, Field::Date, 10),
        };
    }

    /**
     * Answers the text of a format written as a literal, or null for any other format; a NULL literal is the empty format.
     *
     * @param bool $numbers Whether a number literal counts as the text it writes
     */
    public function literal(Invocation $call, int $format, bool $numbers): ?string
    {
        $node = $call->nodes[$format] ?? null;
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }

        return match (true) {
            $node instanceof StringLiteral => $node->value(),
            $node instanceof NullLiteral => $numbers ? null : '',
            $numbers && $node instanceof NumberLiteral => $node->text,
            default => null,
        };
    }
}
