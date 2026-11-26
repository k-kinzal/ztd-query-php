<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\CheckTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CheckTable::class)]
#[Medium]
final class CheckTableTest extends TestCase
{
    public function testRenderWritesTheOptions(): void
    {
        self::assertSame('CHECK TABLE t CHANGED MEDIUM', (new Inspection('mysql-5.6.51'))->inspect('check table t changed medium')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->inspect('CHECK TABLE t', [$table]);
        self::assertInstanceOf(CheckTable::class, $operation->statement);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement->tables[0])->table);
    }
}
