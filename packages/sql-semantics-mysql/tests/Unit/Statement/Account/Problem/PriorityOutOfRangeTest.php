<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange;

#[CoversClass(PriorityOutOfRange::class)]
#[Small]
final class PriorityOutOfRangeTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Thread priority 3 is outside the range of a SYSTEM resource group (ER_INVALID_THREAD_PRIORITY).', (new PriorityOutOfRange('SYSTEM', '3'))->message());
    }
}
