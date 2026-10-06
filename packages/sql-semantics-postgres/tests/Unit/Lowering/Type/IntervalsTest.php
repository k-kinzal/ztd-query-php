<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Type\Intervals;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields;

#[CoversClass(Intervals::class)]
#[Small]
final class IntervalsTest extends TestCase
{
    public function testIntervalLowersTheFieldRestrictionAndItsSecondsPrecision(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT interval '1' day to second (3)");
        $interval = (new Intervals($lowering))->interval($tree->find('ConstInterval')[0], $tree->find('opt_interval')[0]);
        self::assertSame(IntervalFields::DayToSecond, $interval->fields);
        self::assertSame('3', $interval->precision?->digits);
    }

    public function testIntervalLowersARestrictionWithoutSeconds(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT interval '1' year to month");
        $interval = (new Intervals($lowering))->interval($tree->find('ConstInterval')[0], $tree->find('opt_interval')[0]);
        self::assertSame([IntervalFields::YearToMonth, null], [$interval->fields, $interval->precision]);
    }

    public function testIntervalLowersNoRestriction(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT interval '1'");
        $interval = (new Intervals($lowering))->interval($tree->find('ConstInterval')[0], $tree->find('opt_interval')[0]);
        self::assertSame([null, null], [$interval->fields, $interval->precision]);
    }

    public function testPreciseLowersThePrecisionWithoutFields(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT interval (2) '1'");
        $interval = (new Intervals($lowering))->precise($tree->find('ConstInterval')[0], $tree->find('Iconst')[0]);
        self::assertSame([null, '2'], [$interval->fields, $interval->precision?->digits]);
    }

    public function testSecondLowersThePrecisionOrNothing(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT interval '1' second (4)");
        self::assertSame('4', (new Intervals($lowering))->second($tree->find('interval_second')[0])?->digits);
        self::assertNull((new Intervals($lowering))->second((new PostgreSqlParser('pg-17.2'))->parse("SELECT interval '1' second")->find('interval_second')[0]));
    }

    public function testKeywordAcceptsTheIntervalKeywordOnly(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse("SELECT interval '1'");
        (new Intervals($lowering))->keyword($tree->find('ConstInterval')[0]);
        $this->expectExceptionMessage('No semantic rule is implemented for: opt_interval:');
        (new Intervals($lowering))->keyword($tree->find('opt_interval')[0]);
    }
}
