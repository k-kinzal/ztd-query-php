<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(RenameTable::class)]
#[Medium]
final class RenameTableTest extends TestCase
{
    public function testDeriveStatementFollowsTheRenamesInOrder(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = $semantics->analyze('CREATE TABLE t (a INT)');
        $u = $semantics->analyze('CREATE TABLE u (a INT)');
        $rename = $semantics->analyze('RENAME TABLE t TO tmp, u TO t, tmp TO u', [$t, $u]);

        self::assertInstanceOf(RenameTable::class, $rename->statement);
        self::assertSame([], $rename->facts->diagnostics);
        self::assertEquals(new DeclaredTable($t->declarations()[0]), $rename->facts->relation($rename->statement->renamings[2])->table);
    }

    public function testRenderWritesTheRenames(): void
    {
        self::assertSame('RENAME TABLE a TO b, c TO d', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('rename tables a to b, c to d')->toString());
    }
}
