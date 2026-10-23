<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Program\Flow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Flow::class)]
#[Small]
final class FlowTest extends TestCase
{
    public function testCasesNameEveryTransferOfControl(): void
    {
        self::assertSame(['Leave', 'Iterate', 'Return', 'Exit', 'Resume'], array_map(static fn (Flow $flow): string => $flow->name, Flow::cases()));
    }
}
