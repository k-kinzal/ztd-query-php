<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\WindowProblemKind;

#[CoversClass(WindowProblemKind::class)]
#[Small]
final class WindowProblemKindTest extends TestCase
{
    public function testCasesCarryTheServerMessages(): void
    {
        self::assertSame('RANGE with offset PRECEDING/FOLLOWING requires exactly one ORDER BY column', WindowProblemKind::RangeOffsetOrder->value);
    }
}
