<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(DropTable::class)]
#[Medium]
final class DropTableTest extends TestCase
{
    public function testDeriveStatementResolvesEveryTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $drop = $semantics->analyze('DROP TABLE t, u, t', [$table]);

        self::assertInstanceOf(DropTable::class, $drop->statement);
        self::assertInstanceOf(DeclaredTable::class, $drop->facts->relation($drop->statement->tables[0])->table);
        self::assertInstanceOf(MissingTable::class, $drop->facts->relation($drop->statement->tables[1])->table);
        self::assertInstanceOf(RepeatedTable::class, $drop->facts->diagnostics[1]);
    }

    public function testRenderWritesEveryPart(): void
    {
        self::assertSame('DROP TEMPORARY TABLE IF EXISTS t RESTRICT', (new Semantics(Dialect::MySql))->analyze('drop temporary tables if exists t restrict')->toString());
    }
}
