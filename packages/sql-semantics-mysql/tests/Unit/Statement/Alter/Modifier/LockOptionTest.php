<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;

#[CoversClass(LockOption::class)]
#[Medium]
final class LockOptionTest extends TestCase
{
    public function testDeriveOptionAcceptsTheKnownLock(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('DROP INDEX i ON t LOCK = SHARED')->diagnostics());
    }

    public function testDeriveCommandChecksTheLock(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t LOCK = `default`')->diagnostics());
    }

    public function testRenderWritesTheLockOrDefault(): void
    {
        self::assertSame('ALTER TABLE t LOCK = DEFAULT, LOCK = EXCLUSIVE', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t LOCK DEFAULT, LOCK EXCLUSIVE')->toString());
    }
}
