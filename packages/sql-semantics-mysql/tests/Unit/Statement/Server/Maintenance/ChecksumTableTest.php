<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\ChecksumTable;

#[CoversClass(ChecksumTable::class)]
#[Medium]
final class ChecksumTableTest extends TestCase
{
    public function testRenderWritesTheMode(): void
    {
        self::assertSame('CHECKSUM TABLE t, u QUICK', (new Semantics())->analyze('checksum table t, u quick')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Semantics();
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->analyze('CHECKSUM TABLE t', [$table]);
        self::assertInstanceOf(ChecksumTable::class, $operation->statement);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement->tables[0])->table);
    }
}
