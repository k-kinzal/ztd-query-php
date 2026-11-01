<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Time;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Time\DeferredClock;

#[CoversClass(DeferredClock::class)]
#[Medium]
final class DeferredClockTest extends TestCase
{
    public function testDeriveScalarRetainsTheConditionWithoutReportingItAtDefinition(): void
    {
        $value = new DeferredClock(Clock::Now, new Numeral('263'));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());

        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOperandsHasNoRuntimeDependencies(): void
    {
        self::assertSame([], (new DeferredClock(Clock::Now, new Numeral('7')))->operands());
    }

    public function testProblemNamesTheEffectivePrecisionAndTheSelectedClock(): void
    {
        $problem = (new DeferredClock(Clock::UtcTimestamp, new Numeral('00000000263')))->problem();

        self::assertSame([7, 'utc_timestamp', 6], [$problem->precision, $problem->function, $problem->maximum]);
    }

    public function testRenderPreservesTheCompletePrecisionOperand(): void
    {
        $out = new \SqlSemantics\Rendering\Output((new \SqlSemantics\Platform\MySql\Platform())->codec((new Semantics(Dialect::MySql))->profile()));
        (new DeferredClock(Clock::Now, new Numeral('000263')))->render($out);

        self::assertSame('NOW(000263)', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testDeferredDomainExcludesAnOrdinaryPrecision(): void
    {
        $this->expectExceptionMessage('A deferred clock retains a NUM precision whose effective value exceeds six.');
        new DeferredClock(Clock::Now, new Numeral('262'));
    }
}
