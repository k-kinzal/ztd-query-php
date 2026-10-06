<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Load;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadLock;

#[CoversClass(LoadLock::class)]
#[Small]
final class LoadLockTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(['LOW_PRIORITY', 'CONCURRENT'], array_map(static fn (LoadLock $lock): string => $lock->value, LoadLock::cases()));
    }
}
