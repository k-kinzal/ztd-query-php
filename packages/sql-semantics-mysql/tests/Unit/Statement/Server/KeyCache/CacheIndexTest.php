<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\KeyCache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CacheIndex::class)]
#[Medium]
final class CacheIndexTest extends TestCase
{
    public function testRenderWritesDefault(): void
    {
        self::assertSame('CACHE INDEX t IN DEFAULT', (new Inspection())->inspect('cache index t in default')->toString());
    }

    public function testDeriveStatementResolvesEachTable(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a INT, b INT)');
        $operation = $semantics->inspect('CACHE INDEX t IN c', [$table]);
        self::assertInstanceOf(CacheIndex::class, $operation->statement);

        self::assertInstanceOf(DeclaredTable::class, $operation->facts->relation($operation->statement->tables[0])->table);
    }
}
