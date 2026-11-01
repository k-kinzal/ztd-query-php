<?php

declare(strict_types=1);

namespace Tests\Unit\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\System\Performance\InnoDbMonitor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(InnoDbMonitor::class)]
#[Small]
final class InnoDbMonitorTest extends TestCase
{
    public function testReportRetainsTheMonitorSectionsAndCurrentProcessIdentity(): void
    {
        $report = (new InnoDbMonitor())->report(new Instance('8.4.7'));
        self::assertStringContainsString('INNODB MONITOR OUTPUT', $report);
        self::assertStringContainsString('Process ID=' . (string) getmypid(), $report);
        self::assertStringContainsString("0 OS file reads, 0 OS file writes, 0 OS fsyncs\n", $report);
        self::assertStringContainsString("0 read views open inside InnoDB\n", $report);
        self::assertStringEndsWith("END OF INNODB MONITOR OUTPUT\n============================\n", $report);
    }

    public function testTransactionsReflectTheInstanceTransactionState(): void
    {
        $instance = new Instance('8.4.7', [], ['d']);
        $session = $instance->connect(database: 'd');
        $session->query('CREATE TABLE t (id INT PRIMARY KEY); BEGIN; INSERT INTO t VALUES (1)');
        $report = (new InnoDbMonitor())->transactions($instance);
        self::assertStringContainsString('Trx id counter ' . $instance->transactions->sequence, $report);
        self::assertStringContainsString('---TRANSACTION ' . $session->transaction->id . ', ACTIVE 0 sec', $report);
        $session->query('ROLLBACK');
        self::assertStringNotContainsString(', ACTIVE 0 sec', (new InnoDbMonitor())->transactions($instance));
    }

    public function testLogReportsZeroPhysicalRedoActivityForTheRelease(): void
    {
        $monitor = new InnoDbMonitor();
        self::assertStringContainsString("Log buffer assigned up to 0\n", $monitor->log(new Instance('8.4.7')));
        self::assertStringNotContainsString('Log buffer assigned up to', $monitor->log(new Instance('5.7.44')));
        self::assertStringContainsString("0 log i/o's done, 0.00 log i/o's/second\n", $monitor->log(new Instance('8.4.7')));
    }

    public function testBuffersReportNoNativePageAllocationsOrIo(): void
    {
        $report = (new InnoDbMonitor())->buffers();
        self::assertStringContainsString("Buffer pool size 0\n", $report);
        self::assertStringContainsString("Pages read 0, created 0, written 0\n", $report);
        self::assertStringContainsString("Pending reads 0\n", $report);
    }
}
