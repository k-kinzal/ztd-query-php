<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(CheckTable::class)]
#[Medium]
final class CheckTableTest extends TestCase
{
    public function testRenderWritesTheOptions(): void
    {
        self::assertSame('CHECK TABLE t CHANGED MEDIUM', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('check table t changed medium')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->analyze('CHECK TABLE t', [$table]);
        self::assertInstanceOf(CheckTable::class, $operation->statement);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement->tables[0])->table);
    }
}
