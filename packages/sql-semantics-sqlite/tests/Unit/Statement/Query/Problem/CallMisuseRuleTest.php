<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\CallMisuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\CallMisuseRule;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(CallMisuseRule::class)]
#[Medium]
final class CallMisuseRuleTest extends TestCase
{
    public function testCasesTellWhichRuleACallBreaks(): void
    {
        $semantics = new Inspection();
        $window = $semantics->diagnose('SELECT row_number()')->facts->diagnostics[0];
        $scalar = $semantics->diagnose('SELECT abs(1) OVER ()')->facts->diagnostics[0];
        $filter = $semantics->diagnose('SELECT abs(1) FILTER (WHERE 1)')->facts->diagnostics[0];

        self::assertInstanceOf(CallMisuse::class, $window);
        self::assertSame(CallMisuseRule::WindowWithoutOver, $window->rule);
        self::assertInstanceOf(CallMisuse::class, $scalar);
        self::assertSame(CallMisuseRule::ScalarAsWindow, $scalar->rule);
        self::assertInstanceOf(CallMisuse::class, $filter);
        self::assertSame(CallMisuseRule::FilterWithoutAggregate, $filter->rule);
        self::assertCount(7, CallMisuseRule::cases());
    }
}
