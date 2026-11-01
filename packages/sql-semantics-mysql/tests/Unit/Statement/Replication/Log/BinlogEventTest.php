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
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze("BINLOG 'AAAA'")->diagnostics());
    }

    public function testRenderWritesTheEvents(): void
    {
        self::assertSame("BINLOG 'AAAA'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("binlog 'AAAA'")->toString());
    }

    public function testValueRejectsIncompatibleEvents(): void
    {
        $this->expectExceptionMessage('Encoded binary log events are quoted text.');
        new BinlogEvent(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('41', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }
}
