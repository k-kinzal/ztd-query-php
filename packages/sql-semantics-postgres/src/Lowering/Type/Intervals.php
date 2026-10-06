<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Type;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields;

/**
 * Lowers the interval type and its field restriction.
 *
 * Rule: PG-TYPE-INTERVAL-LOWER-001. Scope: `ConstInterval`, `opt_interval`,
 * `interval_second`. Constructor: `IntervalDesignation`. Termination: no
 * recursion. Source: https://www.postgresql.org/docs/17/datatype-datetime.html#DATATYPE-INTERVAL-INPUT.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Intervals
{
    /**
     * The fields each restriction without a seconds part selects.
     */
    private const FIELDS = [
        'opt_interval: YEAR_P' => IntervalFields::Year, 'opt_interval: MONTH_P' => IntervalFields::Month, 'opt_interval: DAY_P' => IntervalFields::Day,
        'opt_interval: HOUR_P' => IntervalFields::Hour, 'opt_interval: MINUTE_P' => IntervalFields::Minute,
        'opt_interval: YEAR_P TO MONTH_P' => IntervalFields::YearToMonth, 'opt_interval: DAY_P TO HOUR_P' => IntervalFields::DayToHour,
        'opt_interval: DAY_P TO MINUTE_P' => IntervalFields::DayToMinute, 'opt_interval: HOUR_P TO MINUTE_P' => IntervalFields::HourToMinute,
    ];

    /**
     * The fields each restriction that ends in seconds selects, with the position of its seconds part.
     */
    private const SECONDS = [
        'opt_interval: interval_second' => [IntervalFields::Second, 0], 'opt_interval: DAY_P TO interval_second' => [IntervalFields::DayToSecond, 2],
        'opt_interval: HOUR_P TO interval_second' => [IntervalFields::HourToSecond, 2], 'opt_interval: MINUTE_P TO interval_second' => [IntervalFields::MinuteToSecond, 2],
    ];

    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `ConstInterval opt_interval`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function interval(Node $keyword, Node $restriction): IntervalDesignation
    {
        $this->keyword($keyword);
        $form = $this->lowering->productions->form($restriction);
        if ($form->signature === 'opt_interval:') {
            return new IntervalDesignation();
        }
        if (isset(self::FIELDS[$form->signature])) {
            return new IntervalDesignation(self::FIELDS[$form->signature]);
        }
        [$fields, $position] = self::SECONDS[$form->signature] ?? throw ImplementationGap::production($form);

        return new IntervalDesignation($fields, $this->second($form->node($position)));
    }

    /**
     * Lowers `ConstInterval ( Iconst )`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function precise(Node $keyword, Node $precision): IntervalDesignation
    {
        $this->keyword($keyword);

        return new IntervalDesignation(null, $this->lowering->literals->integer($precision));
    }

    /**
     * Lowers `interval_second` into its precision.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function second(Node $second): ?IntegerConstant
    {
        $form = $this->lowering->productions->form($second);

        return match ($form->signature) {
            'interval_second: SECOND_P' => null,
            'interval_second: SECOND_P ( Iconst )' => $this->lowering->literals->integer($form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Checks the `ConstInterval` production.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function keyword(Node $keyword): void
    {
        $form = $this->lowering->productions->form($keyword);
        if ($form->signature !== 'ConstInterval: INTERVAL') {
            throw ImplementationGap::production($form);
        }
    }
}
