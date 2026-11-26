<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Group;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Group\StopGroupReplication;
use SqlSemantics\Testing\MySql\InspectedStatement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(StopGroupReplication::class)]
#[Medium]
final class StopGroupReplicationTest extends TestCase
{
    public function testDeriveStatementRejectsMySql56(): void
    {
        $this->expectExceptionMessage('Group replication needs MySQL 5.7 or later.');

        new InspectedStatement((new Inspection('mysql-5.6.51'))->context([]), new StopGroupReplication());
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('STOP GROUP_REPLICATION', (new Inspection('mysql-5.7.44'))->inspect('stop group_replication')->toString());
    }
}
