<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Type\TypeDescriptor;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Derives the result type of date arithmetic with an interval.
 *
 * Rule: MYSQL-TEMPORAL-RESULT-001. The result is a DATE when the operand is
 * a DATE and the unit has date parts only (YEAR, QUARTER, MONTH, WEEK, DAY,
 * YEAR_MONTH); a DATETIME when the operand is a DATETIME or TIMESTAMP, a
 * DATE with a unit that has time parts, or a TIME with a unit that has
 * date parts; from MySQL 8.0.28 a TIME when the operand is a TIME and the
 * unit has time parts only, which earlier releases return as a string; and
 * a string (VARCHAR) for every other operand, including a bare NULL. A
 * missing or invalid operand type decides the result (MYSQL-TYPE-ALTERNATIVES-001).
 * Terminates: no recursion.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html#function_date-add,
 * https://dev.mysql.com/doc/refman/5.7/en/date-and-time-functions.html#function_date-add.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TemporalResult
{
    /**
     * The units that have date parts only.
     */
    private const DATE_UNITS = [
        IntervalUnit::Year, IntervalUnit::Quarter, IntervalUnit::Month, IntervalUnit::Week, IntervalUnit::Day, IntervalUnit::YearMonth,
    ];

    /**
     * The units that have time parts only.
     */
    private const TIME_UNITS = [
        IntervalUnit::Microsecond, IntervalUnit::Second, IntervalUnit::Minute, IntervalUnit::Hour, IntervalUnit::SecondMicrosecond,
        IntervalUnit::MinuteMicrosecond, IntervalUnit::MinuteSecond, IntervalUnit::HourMicrosecond, IntervalUnit::HourSecond, IntervalUnit::HourMinute,
    ];

    /**
     * Answers the type of an operand of a type plus or minus an interval of a unit.
     */
    public function interval(TypeFact $operand, IntervalUnit $unit, GrammarRelease $release): TypeFact
    {
        $alternatives = new Alternatives();
        $blocking = $alternatives->blocking([$operand]);
        if ($blocking !== null) {
            return $blocking;
        }
        $results = [];
        foreach ($alternatives->of($operand) as $type) {
            $results[] = $this->result($type, $unit, $release);
        }

        return $alternatives->known($results);
    }

    /**
     * Answers the result type for one operand type; null is a bare NULL.
     */
    public function result(?TypeDescriptor $type, IntervalUnit $unit, GrammarRelease $release): TypeDescriptor
    {
        if (!$type instanceof Temporal) {
            return new Character(CharacterKind::VarChar);
        }
        $dateOnly = in_array($unit, self::DATE_UNITS, true);

        return match ($type->kind) {
            TemporalKind::Date => new Temporal($dateOnly ? TemporalKind::Date : TemporalKind::DateTime),
            TemporalKind::DateTime, TemporalKind::Timestamp => new Temporal(TemporalKind::DateTime),
            TemporalKind::Time => $this->time($unit, $release),
            TemporalKind::Year => new Character(CharacterKind::VarChar),
        };
    }

    /**
     * Answers the result type for a TIME operand.
     */
    public function time(IntervalUnit $unit, GrammarRelease $release): TypeDescriptor
    {
        if (!in_array($unit, self::TIME_UNITS, true)) {
            return new Temporal(TemporalKind::DateTime);
        }
        $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;

        return $legacy ? new Character(CharacterKind::VarChar) : new Temporal(TemporalKind::Time);
    }
}
