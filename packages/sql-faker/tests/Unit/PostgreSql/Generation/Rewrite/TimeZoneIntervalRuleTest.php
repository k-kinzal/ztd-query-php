<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\PostgreSql\Generation\Rewrite\TimeZoneIntervalRule;

#[CoversClass(TimeZoneIntervalRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class TimeZoneIntervalRuleTest extends TestCase
{
    public function testRewriteAppliesOnlyToTimeZoneIntervals(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('zone_value'), new NonTerminal('opt_interval')]), 0);
        $trace->expand(0, new Production([new Terminal('INTERVAL'), new Terminal('SCONST'), new NonTerminal('opt_interval')]), 0);
        $trace->expand(2, new Production([new Terminal('YEAR_P'), new Terminal('TO'), new Terminal('MONTH_P')]), 0);
        $trace->expand(5, new Production([new Terminal('YEAR_P')]), 0);
        $input = $trace->terminals();
        $result = (new TimeZoneIntervalRule())->rewrite($input);
        self::assertSame(['INTERVAL', 'SCONST', 'HOUR_P', 'YEAR_P'], $result->names());
        self::assertSame($result, (new TimeZoneIntervalRule())->rewrite($result));
    }
}
