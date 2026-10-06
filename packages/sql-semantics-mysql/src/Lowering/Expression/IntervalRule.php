<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Expression;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;

/**
 * Lowers interval units.
 *
 * Rule: MYSQL-INTERVAL-UNIT-001. Scope: interval, interval_time_stamp. The
 * `SQL_TSI_` spellings are the same keywords to the lexer. Constructs:
 * IntervalUnit. Terminates: one unit production is forwarded at most once.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/expressions.html#temporal-intervals.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class IntervalRule
{
    /**
     * The unit productions.
     */
    private const UNITS = [
        'interval: DAY_HOUR_SYM' => IntervalUnit::DayHour, 'interval: DAY_MICROSECOND_SYM' => IntervalUnit::DayMicrosecond,
        'interval: DAY_MINUTE_SYM' => IntervalUnit::DayMinute, 'interval: DAY_SECOND_SYM' => IntervalUnit::DaySecond,
        'interval: HOUR_MICROSECOND_SYM' => IntervalUnit::HourMicrosecond, 'interval: HOUR_MINUTE_SYM' => IntervalUnit::HourMinute,
        'interval: HOUR_SECOND_SYM' => IntervalUnit::HourSecond, 'interval: MINUTE_MICROSECOND_SYM' => IntervalUnit::MinuteMicrosecond,
        'interval: MINUTE_SECOND_SYM' => IntervalUnit::MinuteSecond, 'interval: SECOND_MICROSECOND_SYM' => IntervalUnit::SecondMicrosecond,
        'interval: YEAR_MONTH_SYM' => IntervalUnit::YearMonth, 'interval_time_stamp: DAY_SYM' => IntervalUnit::Day,
        'interval_time_stamp: WEEK_SYM' => IntervalUnit::Week, 'interval_time_stamp: HOUR_SYM' => IntervalUnit::Hour,
        'interval_time_stamp: MINUTE_SYM' => IntervalUnit::Minute, 'interval_time_stamp: MONTH_SYM' => IntervalUnit::Month,
        'interval_time_stamp: QUARTER_SYM' => IntervalUnit::Quarter, 'interval_time_stamp: SECOND_SYM' => IntervalUnit::Second,
        'interval_time_stamp: MICROSECOND_SYM' => IntervalUnit::Microsecond, 'interval_time_stamp: YEAR_SYM' => IntervalUnit::Year,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of interval or interval_time_stamp.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function unit(Node $unit): IntervalUnit
    {
        $form = $this->lowering->form($unit);
        if ($form->signature === 'interval: interval_time_stamp') {
            $form = $this->lowering->form($form->node(0));
        }

        return self::UNITS[$form->signature] ?? throw ImplementationGap::production($form);
    }
}
