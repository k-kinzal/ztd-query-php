<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Log;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent;

#[CoversClass(BinlogEvent::class)]
#[Medium]
final class BinlogEventTest extends TestCase
{
    public function testDeriveStatementRecordsNoDiagnostic(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze("BINLOG 'AAAA'")->facts->diagnostics);
    }

    public function testRenderWritesTheEvents(): void
    {
        self::assertSame("BINLOG 'AAAA'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("binlog 'AAAA'")->toString());
    }
}
