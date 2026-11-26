<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinaryLogs;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowBinaryLogs::class)]
#[Medium]
final class ShowBinaryLogsTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection('mysql-5.7.44'))->inspect('SHOW MASTER LOGS');
        self::assertInstanceOf(ShowBinaryLogs::class, $show->statement);
        self::assertCount(2, $show->fields() ?? []);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW BINARY LOGS', (new Inspection('mysql-5.7.44'))->inspect('SHOW MASTER LOGS')->toString());
    }
}
