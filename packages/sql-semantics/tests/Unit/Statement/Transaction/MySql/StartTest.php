<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Transaction\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Transaction\MySql\Access;
use SqlSemantics\Statement\Transaction\MySql\Start;

#[CoversClass(Start::class)]
#[Small]
final class StartTest extends TestCase
{
    public function testWithAccessKeepsTheOriginalRequestAndItsSnapshotPolicy(): void
    {
        $original = new Start(true, Access::ReadOnly);
        $updated = $original->withAccess(Access::ReadWrite);
        self::assertSame(Access::ReadOnly, $original->access);
        self::assertSame(Access::ReadWrite, $updated->access);
        self::assertTrue($updated->consistentSnapshot);
    }

    #[TestWith([false, Access::SessionDefault, 'START TRANSACTION'])]
    #[TestWith([true, Access::SessionDefault, 'START TRANSACTION WITH CONSISTENT SNAPSHOT'])]
    #[TestWith([false, Access::ReadOnly, 'START TRANSACTION READ ONLY'])]
    #[TestWith([true, Access::ReadWrite, 'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ WRITE'])]
    #[TestWith([false, Access::Conflicting, 'START TRANSACTION READ ONLY, READ WRITE'])]
    public function testToStringRetainsSemanticRequirements(bool $snapshot, Access $access, string $expected): void
    {
        self::assertSame($expected, (new Start($snapshot, $access))->toString());
    }
}
