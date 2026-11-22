<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowBinlogEvents::class)]
#[Medium]
final class ShowBinlogEventsTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection('mysql-5.6.51'))->inspect('SHOW BINLOG EVENTS FROM 4');
        self::assertInstanceOf(ShowBinlogEvents::class, $show->statement);
        self::assertSame('Pos', $show->field(1)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW BINLOG EVENTS FROM 4', (new Inspection('mysql-5.6.51'))->inspect('SHOW BINLOG EVENTS FROM 4')->toString());
    }
}
