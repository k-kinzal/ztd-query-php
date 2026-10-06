<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\TemporalResult;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;

#[CoversClass(TemporalResult::class)]
#[Small]
final class TemporalResultTest extends TestCase
{
    public function testIntervalTypesEachOperand(): void
    {
        $results = new TemporalResult();
        $missing = new Dependent([new SessionState('x')]);

        self::assertEquals(new Known(new Temporal(TemporalKind::DateTime)), $results->interval(new Known(new Temporal(TemporalKind::Timestamp)), IntervalUnit::Day, GrammarRelease::MySql847));
        self::assertEquals(new Known(new Character(CharacterKind::VarChar)), $results->interval(new NullOnly(), IntervalUnit::Day, GrammarRelease::MySql847));
        self::assertEquals($missing, $results->interval($missing, IntervalUnit::Day, GrammarRelease::MySql847));
    }

    public function testResultKeepsADateForADateUnit(): void
    {
        $results = new TemporalResult();

        self::assertEquals([new Temporal(TemporalKind::Date), new Temporal(TemporalKind::DateTime)], [$results->result(new Temporal(TemporalKind::Date), IntervalUnit::YearMonth, GrammarRelease::MySql847), $results->result(new Temporal(TemporalKind::Date), IntervalUnit::DayHour, GrammarRelease::MySql847)]);
    }

    public function testTimeKeepsATimeFromMySql80(): void
    {
        $results = new TemporalResult();

        self::assertEquals(
            [new Temporal(TemporalKind::Time), new Character(CharacterKind::VarChar), new Temporal(TemporalKind::DateTime)],
            [$results->time(IntervalUnit::HourMinute, GrammarRelease::MySql847), $results->time(IntervalUnit::Hour, GrammarRelease::MySql5651), $results->time(IntervalUnit::Week, GrammarRelease::MySql847)],
        );
    }
}
