<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents;

#[CoversClass(ShowBinlogEvents::class)]
#[Medium]
final class ShowBinlogEventsTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW BINLOG EVENTS FROM 4');
        self::assertInstanceOf(ShowBinlogEvents::class, $show->statement);
        self::assertSame('Pos', $show->field(1)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW BINLOG EVENTS FROM 4', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW BINLOG EVENTS FROM 4')->toString());
    }
}
