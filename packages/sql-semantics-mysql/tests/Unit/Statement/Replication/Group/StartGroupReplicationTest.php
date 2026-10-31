<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication;

#[CoversClass(StartGroupReplication::class)]
#[Medium]
final class StartGroupReplicationTest extends TestCase
{
    public function testDeriveStatementAcceptsExplicitCredentials(): void
    {
        $start = (new Semantics(Dialect::MySql))->analyze("START GROUP_REPLICATION USER = 'u', PASSWORD = 'p'");
        self::assertSame([], $start->diagnostics());
    }

    public function testRenderRetainsConflictingCredentials(): void
    {
        $start = (new Semantics(Dialect::MySql))->analyze("START GROUP_REPLICATION PASSWORD = 'p'");
        self::assertInstanceOf(StartGroupReplication::class, $start);
        self::assertSame("START GROUP_REPLICATION PASSWORD = 'p'", $start->toString());
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting(\SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::GroupUserMissing), $start->diagnostics()[0]);
    }

    public function testDeriveStatementRejectsMySql56(): void
    {
        $this->expectExceptionMessage('Group replication needs MySQL 5.7 or later.');

        (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([]), new StartGroupReplication(), new \SqlSemantics\Statement\Source\SourceMap());
    }

    public function testRenderWritesTheBareStatement(): void
    {
        self::assertSame('START GROUP_REPLICATION', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('start group_replication')->toString());
    }
}
