<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\RepairTable;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RepairTable::class)]
#[Medium]
final class RepairTableTest extends TestCase
{
    public function testRenderWritesTheOptions(): void
    {
        self::assertSame('REPAIR TABLE t EXTENDED', (new Inspection('mysql-5.7.44'))->diagnose('repair table t extended')->toString());
    }

    public function testDeriveStatementReportsATableNamedTwice(): void
    {
        $operation = (new Inspection())->diagnose('REPAIR TABLE t, db.u, t');

        self::assertInstanceOf(NonUniqueTable::class, $operation->facts->diagnostics[0]);
    }
}
