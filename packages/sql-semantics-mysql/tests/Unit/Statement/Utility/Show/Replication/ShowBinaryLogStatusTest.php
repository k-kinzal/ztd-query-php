<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinaryLogStatus;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowBinaryLogStatus::class)]
#[Medium]
final class ShowBinaryLogStatusTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection('mysql-8.2.0'))->inspect('SHOW MASTER STATUS');
        self::assertInstanceOf(ShowBinaryLogStatus::class, $show->statement);
        self::assertSame('Executed_Gtid_Set', $show->field(4)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW MASTER STATUS', (new Inspection('mysql-8.2.0'))->inspect('SHOW MASTER STATUS')->toString());
    }
}
