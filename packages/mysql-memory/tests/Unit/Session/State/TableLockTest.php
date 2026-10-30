<?php

declare(strict_types=1);

namespace Tests\Unit\Session\State;

use MySqlMemory\Session\State\TableLock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableLock::class)]
#[Small]
final class TableLockTest extends TestCase
{
    public function testRenamedMovesAnImplicitAccessName(): void
    {
        $lock = new TableLock('d', 'old', 'old', true);

        self::assertEquals(new TableLock('other', 'new', 'new', true), $lock->renamed('other', 'new'));
        self::assertSame('old', $lock->table);
    }

    public function testRenamedRetainsAnExplicitAliasAndReadMode(): void
    {
        $lock = new TableLock('d', 'old', 'alias', false);

        self::assertEquals(new TableLock('d', 'new', 'alias', false), $lock->renamed('d', 'new'));
    }
}
