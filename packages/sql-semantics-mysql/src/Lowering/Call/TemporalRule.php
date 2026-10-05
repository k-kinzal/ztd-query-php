<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Call;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\Extract;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\DateArithmetic;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\GetFormat;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TemporalFormat;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampCall;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampOperation;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Statement\Scalar;

/**
 * Lowers the date and time functions with a special syntax: the clock functions, interval arithmetic, EXTRACT, GET_FORMAT, TIMESTAMPADD and TIMESTAMPDIFF.
 *
 * Rule: MYSQL-CALL-TEMPORAL-001. Scope: now, func_datetime_precision,
 * date_time_type and the alternatives of function_call_nonkeyword for
 * CURDATE, CURTIME, NOW, SYSDATE, UTC_DATE, UTC_TIME, UTC_TIMESTAMP,
 * DATE_ADD, DATE_SUB, ADDDATE and SUBDATE with INTERVAL, EXTRACT,
 * GET_FORMAT, TIMESTAMPADD and TIMESTAMPDIFF. Empty parentheses after a
 * clock function are optional and change nothing; whether they are written
 * is kept (OptionalWords), since it is part of the text MySQL names an
 * unaliased select list expression after;
 * ADDDATE and SUBDATE with INTERVAL are DATE_ADD and DATE_SUB (CallNoise).
 * Constructs: ClockCall, DateArithmetic, Extract, GetFormat, TimestampCall.
 * Terminates: every child is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TemporalRule
{
    /**
     * The clock productions: the function and whether its suffix is a precision rather than optional parentheses.
     */
    private const CLOCKS = [
        'function_call_nonkeyword: CURDATE optional_braces' => [Clock::CurrentDate, false],
        'function_call_nonkeyword: UTC_DATE_SYM optional_braces' => [Clock::UtcDate, false],
        'function_call_nonkeyword: CURTIME func_datetime_precision' => [Clock::CurrentTime, true],
        'function_call_nonkeyword: SYSDATE func_datetime_precision' => [Clock::SystemDate, true],
        'function_call_nonkeyword: UTC_TIME_SYM func_datetime_precision' => [Clock::UtcTime, true],
        'function_call_nonkeyword: UTC_TIMESTAMP_SYM func_datetime_precision' => [Clock::UtcTimestamp, true],
    ];

    /**
     * The interval arithmetic productions, by whether they subtract.
     */
    private const ARITHMETIC = [
        'function_call_nonkeyword: ADDDATE_SYM ( expr , INTERVAL_SYM expr interval )' => false,
        'function_call_nonkeyword: DATE_ADD_INTERVAL ( expr , INTERVAL_SYM expr interval )' => false,
        'function_call_nonkeyword: DATE_SUB_INTERVAL ( expr , INTERVAL_SYM expr interval )' => true,
        'function_call_nonkeyword: SUBDATE_SYM ( expr , INTERVAL_SYM expr interval )' => true,
    ];

    /**
     * The kinds GET_FORMAT takes.
     */
    private const FORMATS = [
        'date_time_type: DATE_SYM' => TemporalFormat::Date, 'date_time_type: TIME_SYM' => TemporalFormat::Time,
        'date_time_type: TIMESTAMP' => TemporalFormat::Timestamp, 'date_time_type: TIMESTAMP_SYM' => TemporalFormat::Timestamp,
        'date_time_type: DATETIME' => TemporalFormat::DateTime, 'date_time_type: DATETIME_SYM' => TemporalFormat::DateTime,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Tells whether a production is one of this rule.
     */
    public function claims(string $signature): bool
    {
        return isset(self::CLOCKS[$signature]) || isset(self::ARITHMETIC[$signature]) || in_array($signature, [
            'function_call_nonkeyword: now', 'function_call_nonkeyword: EXTRACT_SYM ( interval FROM expr )',
            'function_call_nonkeyword: GET_FORMAT ( date_time_type , expr )', 'function_call_nonkeyword: TIMESTAMP_ADD ( interval_time_stamp , expr , expr )',
            'function_call_nonkeyword: TIMESTAMP_DIFF ( interval_time_stamp , expr , expr )',
        ], true);
    }

    /**
     * Lowers a production this rule claims.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function call(Form $form): Scalar
    {
        if (isset(self::CLOCKS[$form->signature])) {
            [$clock, $precise] = self::CLOCKS[$form->signature];
            if ($precise) {
                return new ClockCall($clock, $this->precision($form->node(1)), $this->parentheses($form->node(1)));
            }

            return new ClockCall($clock, null, $this->lowering->options->present($form->node(1)) ? OptionalWords::Written : OptionalWords::Omitted);
        }
        $expressions = $this->lowering->expressions;
        if (isset(self::ARITHMETIC[$form->signature])) {
            return new DateArithmetic(self::ARITHMETIC[$form->signature], $expressions->expression($form->node(2)), $expressions->expression($form->node(5)), $expressions->intervalUnit($form->node(6)));
        }

        return match ($form->signature) {
            'function_call_nonkeyword: now' => $this->currentTimestamp($form->node(0)),
            'function_call_nonkeyword: EXTRACT_SYM ( interval FROM expr )' => new Extract($expressions->intervalUnit($form->node(2)), $expressions->expression($form->node(4))),
            'function_call_nonkeyword: GET_FORMAT ( date_time_type , expr )' => new GetFormat($this->format($form->node(2)), $expressions->expression($form->node(4))),
            'function_call_nonkeyword: TIMESTAMP_ADD ( interval_time_stamp , expr , expr )' => $this->timestamp($form, TimestampOperation::Add),
            'function_call_nonkeyword: TIMESTAMP_DIFF ( interval_time_stamp , expr , expr )' => $this->timestamp($form, TimestampOperation::Difference),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers TIMESTAMPADD or TIMESTAMPDIFF.
     */
    public function timestamp(Form $form, TimestampOperation $operation): TimestampCall
    {
        $expressions = $this->lowering->expressions;

        return new TimestampCall($operation, $expressions->intervalUnit($form->node(2)), $expressions->expression($form->node(4)), $expressions->expression($form->node(6)));
    }

    /**
     * Lowers the current timestamp function: a node of now.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function currentTimestamp(Node $now): ClockCall
    {
        $form = $this->lowering->form($now);
        if ($form->signature !== 'now: NOW_SYM func_datetime_precision') {
            throw ImplementationGap::production($form);
        }

        return new ClockCall(Clock::Now, $this->precision($form->node(1)), $this->parentheses($form->node(1)));
    }

    /**
     * Answers whether the parentheses of an optional fractional seconds precision are written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function parentheses(Node $precision): OptionalWords
    {
        $form = $this->lowering->form($precision);

        return match ($form->signature) {
            'func_datetime_precision:' => OptionalWords::Omitted,
            'func_datetime_precision: ( )', 'func_datetime_precision: ( NUM )' => OptionalWords::Written,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the optional fractional seconds precision of a clock function; empty parentheses are no precision.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function precision(Node $precision): ?Numeral
    {
        $form = $this->lowering->form($precision);

        return match ($form->signature) {
            'func_datetime_precision:', 'func_datetime_precision: ( )' => null,
            'func_datetime_precision: ( NUM )' => $this->lowering->numbers->token($form->token(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the kind GET_FORMAT takes.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function format(Node $kind): TemporalFormat
    {
        $form = $this->lowering->form($kind);

        return self::FORMATS[$form->signature] ?? throw ImplementationGap::production($form);
    }
}
