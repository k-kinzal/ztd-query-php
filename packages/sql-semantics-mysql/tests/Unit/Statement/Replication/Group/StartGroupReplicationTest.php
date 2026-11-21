<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Operation;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\StartGroupReplication;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSetting;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;

#[CoversClass(StartGroupReplication::class)]
#[Medium]
final class StartGroupReplicationTest extends TestCase
{
    public function testDeriveStatementReportsALongPassword(): void
    {
        $start = (new Semantics())->inspect("START GROUP_REPLICATION PASSWORD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'");

        self::assertEquals([new RefusedSetting(ReplicationError::GroupPasswordTooLong)], $start->facts->diagnostics);
    }

    public function testDeriveStatementRejectsMySql56(): void
    {
        $this->expectExceptionMessage('Group replication needs MySQL 5.7 or later.');

        new Operation((new Semantics('mysql-5.6.51'))->context([]), new StartGroupReplication());
    }

    public function testRenderWritesTheBareStatement(): void
    {
        self::assertSame('START GROUP_REPLICATION', (new Semantics('mysql-5.7.44'))->inspect('start group_replication')->toString());
    }
}
