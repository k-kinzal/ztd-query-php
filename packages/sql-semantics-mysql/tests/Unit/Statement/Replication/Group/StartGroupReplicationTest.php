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

    public function testRenderRequiresConsistentCredentials(): void
    {
        $this->expectExceptionMessage('Group recovery credentials require a nonempty user with a password of at most 32 bytes.');
        new StartGroupReplication([new \SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption(\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::Password, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('p'))]);
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
