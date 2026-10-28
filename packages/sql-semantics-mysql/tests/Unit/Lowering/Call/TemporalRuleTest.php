<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Call\TemporalRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\Extract;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\DateArithmetic;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\GetFormat;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TemporalFormat;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampCall;
use SqlSemantics\Platform\MySql\Statement\Call\Temporal\TimestampOperation;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;

#[CoversClass(TemporalRule::class)]
#[Medium]
final class TemporalRuleTest extends TestCase
{
    public function testTimestampRetainsTheSiblingOfANonScalarOperand(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT TIMESTAMPDIFF(DAY, ROW(1,2), (SELECT a FROM t))')->find('function_call_nonkeyword')[0];
        $value = (new TemporalRule($lowering))->timestamp($lowering->form($node), TimestampOperation::Difference);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredTimestamp::class, $value);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery::class, $value->second);
    }

    public function testArithmeticRetainsBothOperandsAroundAnIntrinsicRowCondition(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT DATE_SUB(ROW(1,2), INTERVAL (SELECT a FROM t) DAY)')->find('function_call_nonkeyword')[0];
        $value = (new TemporalRule($lowering))->arithmetic($lowering->form($node), true);

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredDateArithmetic::class, $value);
        self::assertTrue($value->subtract);
        self::assertSame(IntervalUnit::Day, $value->unit);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery::class, $value->quantity);
    }

    public function testExtractRetainsTheUnitAroundARowSource(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT EXTRACT(DAY FROM ROW(1,2))')->find('function_call_nonkeyword')[0];
        $value = (new TemporalRule($lowering))->extract($lowering->form($node));

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredExtract::class, $value);
        self::assertSame(IntervalUnit::Day, $value->unit);
    }

    public function testGetFormatRetainsTheCompleteDeferredStandard(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse("SELECT GET_FORMAT(DATE, CAST(EXTRACT(DAY FROM NULL) AT TIME ZONE '+00:00' AS DATETIME))")->find('function_call_nonkeyword')[0];
        $value = (new TemporalRule($lowering))->getFormat($lowering->form($node));

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredGetFormat::class, $value);
        self::assertSame(TemporalFormat::Date, $value->format);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredTimeZone::class, $value->standard);
    }

    public function testClaimsTellsTheProductionsOfTheRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $rule = new TemporalRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        self::assertTrue($rule->claims('function_call_nonkeyword: now'));
        self::assertFalse($rule->claims('function_call_keyword: TRIM ( expr )'));
    }

    public function testCallLowersTheTemporalSyntaxes(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $list = $platform->parser($profile)->parse('SELECT UTC_TIME(2), CURRENT_DATE, SUBDATE(a, INTERVAL 1 DAY), EXTRACT(HOUR FROM a), GET_FORMAT(DATETIME, a), TIMESTAMPADD(SQL_TSI_DAY, 1, a), NOW()')->find('select_item_list')[0];
        $rule = new TemporalRule($lowering);
        $calls = array_map(static fn (Node $node): \SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand => $rule->call($lowering->form($node)), $list->find('function_call_nonkeyword'));

        self::assertEquals(new ClockCall(Clock::UtcTime, new Numeral('2', inputSource: new \SqlSemantics\Statement\Source\Span(16, 1))), $calls[0]);
        self::assertEquals(new ClockCall(Clock::CurrentDate, null, OptionalWords::Omitted, alias: \SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias::CurrentDate), $calls[1]);
        self::assertInstanceOf(DateArithmetic::class, $calls[2]);
        self::assertTrue($calls[2]->subtract);
        self::assertInstanceOf(Extract::class, $calls[3]);
        self::assertInstanceOf(GetFormat::class, $calls[4]);
        self::assertSame(TemporalFormat::DateTime, $calls[4]->format);
        self::assertInstanceOf(TimestampCall::class, $calls[5]);
        self::assertSame(IntervalUnit::SqlTsiDay, $calls[5]->unit);
        self::assertEquals(new ClockCall(Clock::Now), $calls[6]);
    }

    public function testCallReportsAProductionOutsideTheRule(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT LEFT(1, 2)')->find('function_call_keyword')[0];
        $this->expectExceptionMessage('No semantic rule is implemented for: ');

        (new TemporalRule($lowering))->call($lowering->form($node));
    }

    public function testTimestampLowersTheUnitAndTheOperands(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT TIMESTAMPDIFF(MONTH, a, b)')->find('function_call_nonkeyword')[0];
        self::assertSame(IntervalUnit::Month, (new TemporalRule($lowering))->timestamp($lowering->form($node), TimestampOperation::Difference)->unit);
    }

    public function testCurrentTimestampLowersNow(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('CREATE TABLE t (c TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP(6))')->find('now')[0];
        self::assertEquals(new ClockCall(Clock::Now, new Numeral('6', inputSource: new \SqlSemantics\Statement\Source\Span(57, 1)), alias: \SqlSemantics\Platform\MySql\Statement\Call\Keyword\ClockAlias::CurrentTimestamp), (new TemporalRule($lowering))->currentTimestamp($node));
    }

    public function testCurrentTimestampReportsAnotherNode(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT 1')->find('select_item_list')[0];
        $this->expectExceptionMessage('No semantic rule is implemented for: ');

        (new TemporalRule($lowering))->currentTimestamp($node);
    }

    public function testPrecisionTreatsEmptyParenthesesAsNoPrecision(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT SYSDATE()')->find('func_datetime_precision')[0];
        self::assertNull((new TemporalRule($lowering))->precision($node));
    }

    public function testParenthesesTellsWhetherTheParenthesesAreWritten(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $tree = $platform->parser($profile)->parse('SELECT CURRENT_TIMESTAMP, NOW(), NOW(3)');
        $precisions = $tree->find('func_datetime_precision');

        self::assertSame([OptionalWords::Omitted, OptionalWords::Written, OptionalWords::Written], array_map(static fn (Node $node): OptionalWords => (new TemporalRule($lowering))->parentheses($node), $precisions));
    }

    public function testFormatLowersTheKind(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT GET_FORMAT(TIME, a)')->find('date_time_type')[0];
        self::assertSame(TemporalFormat::Time, (new TemporalRule($lowering))->format($node));
    }
    public function testClockKeepsAnIntrinsicPrecisionOutsideScalar(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('SELECT NOW(263)')->find('func_datetime_precision')[0];
        $clock = (new TemporalRule($lowering))->clock(Clock::Now, $node);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Time\DeferredClock::class, $clock);
        self::assertSame(7, $clock->problem()->precision);
    }
}
