<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\KeyCache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\LoadIndex;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(LoadIndex::class)]
#[Medium]
final class LoadIndexTest extends TestCase
{
    public function testRenderWritesTheTables(): void
    {
        self::assertSame('LOAD INDEX INTO CACHE t, u', (new Semantics(Dialect::MySql))->analyze('load index into cache t, u')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->analyze('LOAD INDEX INTO CACHE t', [$table]);
        self::assertInstanceOf(LoadIndex::class, $operation->statement);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement->tables[0])->table);
    }
}
