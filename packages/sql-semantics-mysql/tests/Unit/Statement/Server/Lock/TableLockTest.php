<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Lock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TableLock::class)]
#[Medium]
final class TableLockTest extends TestCase
{
    public function testRenderWritesTableAliasAndLock(): void
    {
        self::assertSame('LOCK TABLES db.t a LOW_PRIORITY WRITE', (new Inspection('mysql-5.7.44'))->inspect('lock tables db.t a low_priority write')->toString());
    }
}
