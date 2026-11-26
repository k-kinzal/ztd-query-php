<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LockOption::class)]
#[Medium]
final class LockOptionTest extends TestCase
{
    public function testDeriveOptionReportsALockTheReleaseDoesNotKnow(): void
    {
        self::assertSame('DEFAULT is not a LOCK the server knows.', (new Inspection())->diagnose('DROP INDEX i ON t LOCK = `DEFAULT`')->facts->diagnostics[0]->message());
    }

    public function testDeriveCommandChecksTheLock(): void
    {
        self::assertSame([], (new Inspection('mysql-5.6.51'))->diagnose('ALTER TABLE t LOCK = `default`')->facts->diagnostics);
    }

    public function testRenderWritesTheLockOrDefault(): void
    {
        self::assertSame('ALTER TABLE t LOCK = DEFAULT, LOCK = EXCLUSIVE', (new Inspection())->diagnose('ALTER TABLE t LOCK DEFAULT, LOCK EXCLUSIVE')->toString());
    }
}
