<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use MySqlMemory\Registry\Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Registry::class)]
#[Small]
final class RegistryTest extends TestCase
{
    public function testKeyIgnoresLetterCaseAndAccents(): void
    {
        self::assertSame(Registry::key('admin_e'), Registry::key('Admin_É'));
    }

    public function testThreadsStartWithoutSessionsOrLocks(): void
    {
        $registry = new Registry();

        self::assertSame([[], [], 0.0], [$registry->threads->connected, $registry->threads->locks, $registry->threads->passed]);
    }

    public function testKeyKeepsTrailingSpaces(): void
    {
        self::assertNotSame(Registry::key('a'), Registry::key('a '));
    }

    public function testApplyingIsFalseOnANewServerWithoutObjects(): void
    {
        $registry = new Registry();

        self::assertSame([false, [], [], [], [1]], [$registry->replication->applying, $registry->servers, $registry->tablespaces, $registry->prepared, $registry->binaryLog->files]);
    }
}
