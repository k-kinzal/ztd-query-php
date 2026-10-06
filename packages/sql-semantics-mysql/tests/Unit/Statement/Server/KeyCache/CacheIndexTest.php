<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\KeyCache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(CacheIndex::class)]
#[Medium]
final class CacheIndexTest extends TestCase
{
    public function testRenderWritesDefault(): void
    {
        self::assertSame('CACHE INDEX t IN DEFAULT', (new Semantics(Dialect::MySql))->analyze('cache index t in default')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->analyze('CACHE INDEX t IN c', [$table]);
        self::assertInstanceOf(CacheIndex::class, $operation->statement);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement->tables[0])->table);
    }
}
