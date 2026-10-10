<?php

declare(strict_types=1);

namespace Tests\Unit\Concurrency;

use MySqlMemory\Concurrency\LockMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(LockMode::class)]
#[Small]
final class LockModeTest extends TestCase
{
    public function testAdmitsLetsSharedLocksOfOthersOnly(): void
    {
        self::assertSame([true, false, false, false], [LockMode::Shared->admits(LockMode::Shared), LockMode::Shared->admits(LockMode::Exclusive), LockMode::Exclusive->admits(LockMode::Shared), LockMode::Exclusive->admits(LockMode::Exclusive)]);
    }

    public function testCoversTellsAnExclusiveLockIncludesASharedOne(): void
    {
        self::assertSame([true, false, true, true], [LockMode::Shared->covers(LockMode::Shared), LockMode::Shared->covers(LockMode::Exclusive), LockMode::Exclusive->covers(LockMode::Shared), LockMode::Exclusive->covers(LockMode::Exclusive)]);
    }
}
