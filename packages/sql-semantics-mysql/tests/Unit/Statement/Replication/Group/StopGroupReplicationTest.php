<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Operation;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\StopGroupReplication;

#[CoversClass(StopGroupReplication::class)]
#[Medium]
final class StopGroupReplicationTest extends TestCase
{
    public function testDeriveStatementRejectsMySql56(): void
    {
        $this->expectExceptionMessage('Group replication needs MySQL 5.7 or later.');

        new Operation((new Semantics('mysql-5.6.51'))->context([]), new StopGroupReplication());
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('STOP GROUP_REPLICATION', (new Semantics('mysql-5.7.44'))->analyze('stop group_replication')->toString());
    }
}
