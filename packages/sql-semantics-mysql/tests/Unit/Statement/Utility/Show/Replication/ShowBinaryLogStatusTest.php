<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinaryLogStatus;

#[CoversClass(ShowBinaryLogStatus::class)]
#[Medium]
final class ShowBinaryLogStatusTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-8.2.0'))->analyze('SHOW MASTER STATUS');
        self::assertInstanceOf(ShowBinaryLogStatus::class, $show->statement);
        self::assertSame('Executed_Gtid_Set', $show->field(4)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW MASTER STATUS', (new Semantics(Dialect::MySql, 'mysql-8.2.0'))->analyze('SHOW MASTER STATUS')->toString());
    }
}
