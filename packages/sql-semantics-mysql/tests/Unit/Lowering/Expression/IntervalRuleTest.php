<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Expression\IntervalRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;

#[CoversClass(IntervalRule::class)]
#[Medium]
final class IntervalRuleTest extends TestCase
{
    public function testUnitLowersSimpleAndCompoundUnits(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $rule = new IntervalRule($lowering);

        self::assertSame(IntervalUnit::MinuteMicrosecond, $rule->unit($platform->parser($profile)->parse("SELECT 1 + INTERVAL '1' MINUTE_MICROSECOND")->find('interval')[0]));
        self::assertSame(IntervalUnit::Day, $rule->unit($platform->parser($profile)->parse('SELECT 1 + INTERVAL 1 SQL_TSI_DAY')->find('interval')[0]));
    }
}
