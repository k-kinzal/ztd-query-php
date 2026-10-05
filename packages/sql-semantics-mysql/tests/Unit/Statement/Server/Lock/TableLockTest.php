<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Lock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock;

#[CoversClass(TableLock::class)]
#[Medium]
final class TableLockTest extends TestCase
{
    public function testRenderWritesTableAliasAndLock(): void
    {
        self::assertSame('LOCK TABLES db.t a LOW_PRIORITY WRITE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('lock tables db.t a low_priority write')->toString());
    }
}
