<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\KeyCache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\LoadIndex;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LoadIndex::class)]
#[Medium]
final class LoadIndexTest extends TestCase
{
    public function testRenderWritesTheTables(): void
    {
        self::assertSame('LOAD INDEX INTO CACHE t, u', (new Inspection())->inspect('load index into cache t, u')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->inspect('LOAD INDEX INTO CACHE t', [$table]);
        self::assertInstanceOf(LoadIndex::class, $operation->statement);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement->tables[0])->table);
    }
}
