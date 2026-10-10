<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;
use SqlSemantics\Statement\Operation;

#[CoversClass(StartGroupReplication::class)]
#[Medium]
final class StartGroupReplicationTest extends TestCase
{
    public function testDeriveStatementReportsALongPassword(): void
    {
        $start = (new Semantics(Dialect::MySql))->analyze("START GROUP_REPLICATION PASSWORD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'");

        self::assertEquals([new RefusedSetting(ReplicationError::GroupPasswordTooLong), new RefusedSetting(ReplicationError::GroupUserMissing)], $start->facts->diagnostics);
    }

    public function testDeriveStatementRequiresAUserWithPassword(): void
    {
        $start = (new Semantics(Dialect::MySql))->analyze("START GROUP_REPLICATION PASSWORD = ''");

        self::assertEquals([new RefusedSetting(ReplicationError::GroupUserMissing)], $start->facts->diagnostics);
    }

    public function testDeriveStatementUsesTheLastUserOption(): void
    {
        $start = (new Semantics(Dialect::MySql))->analyze("START GROUP_REPLICATION USER = 'u', USER = '', PASSWORD = 'p'");

        self::assertEquals([new RefusedSetting(ReplicationError::GroupUserEmpty)], $start->facts->diagnostics);
    }

    public function testDeriveStatementRejectsMySql56(): void
    {
        $this->expectExceptionMessage('Group replication needs MySQL 5.7 or later.');

        new Operation((new Semantics(Dialect::MySql, 'mysql-5.6.51'))->context([]), new StartGroupReplication());
    }

    public function testRenderWritesTheBareStatement(): void
    {
        self::assertSame('START GROUP_REPLICATION', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('start group_replication')->toString());
    }
}
