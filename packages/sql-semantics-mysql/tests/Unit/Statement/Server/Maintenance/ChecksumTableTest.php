<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(ChecksumTable::class)]
#[Medium]
final class ChecksumTableTest extends TestCase
{
    public function testRenderWritesTheMode(): void
    {
        self::assertSame('CHECKSUM TABLE t, u QUICK', (new Semantics(Dialect::MySql))->analyze('checksum table t, u quick')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->analyze('CHECKSUM TABLE t', [$table]);
        self::assertInstanceOf(ChecksumTable::class, $operation->statement);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement->tables[0])->table);
    }
}
