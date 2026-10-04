<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowReplicaStatus;

#[CoversClass(ShowReplicaStatus::class)]
#[Medium]
final class ShowReplicaStatusTest extends TestCase
{
    public function testDeriveStatementNamesTheColumnsAfterTheSpelling(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-8.3.0'))->analyze('SHOW SLAVE STATUS');
        self::assertInstanceOf(ShowReplicaStatus::class, $show->statement);
        self::assertSame('Slave_IO_State', $show->field(0)->name?->value);
        self::assertSame('Network_Namespace', $show->field(59)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW SLAVE STATUS', (new Semantics(Dialect::MySql, 'mysql-8.3.0'))->analyze('SHOW SLAVE STATUS')->toString());
    }
}
