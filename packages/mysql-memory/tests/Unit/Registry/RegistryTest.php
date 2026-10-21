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
    public function testDisconnectDiscardsOnlyBranchesOwnedByTheLegacyConnection(): void
    {
        $registry = new Registry();
        $registry->prepared = [
            'one' => new \MySqlMemory\Registry\PreparedBranch(1, 'a', '', owner: 1),
            'two' => new \MySqlMemory\Registry\PreparedBranch(1, 'b', '', owner: 2),
            'detached' => new \MySqlMemory\Registry\PreparedBranch(1, 'c', ''),
        ];
        $registry->disconnect(1, false);
        self::assertCount(3, $registry->prepared);
        $registry->disconnect(1, true);
        self::assertSame(['two', 'detached'], array_keys($registry->prepared));
    }
}
