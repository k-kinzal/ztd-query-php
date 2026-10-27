<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Log;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsTo;

#[CoversClass(PurgeLogsTo::class)]
#[Medium]
final class PurgeLogsToTest extends TestCase
{
    public function testDeriveStatementRecordsNoDiagnostic(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze("PURGE BINARY LOGS TO 'b.000001'")->diagnostics());
    }

    public function testRenderWritesBinaryLogs(): void
    {
        self::assertSame("PURGE BINARY LOGS TO 'b.000001'", (new Semantics(Dialect::MySql, 'mysql-8.3.0'))->analyze("purge master logs to 'b.000001'")->toString());
    }

    public function testValueRejectsIncompatibleFile(): void
    {
        $this->expectExceptionMessage('A binary log file name is quoted text.');
        new PurgeLogsTo(new \SqlSemantics\Platform\MySql\Statement\Literal\Text('41', \SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal));
    }
}
