<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Server\MaintainedTables;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(MaintainedTables::class)]
#[Medium]
final class MaintainedTablesTest extends TestCase
{
    public function testCheckedKeepsTheTables(): void
    {
        $tables = [new MaintainedTable(new QualifiedName(new Name('t')))];

        self::assertSame($tables, (new MaintainedTables())->checked($tables));
    }

    public function testDeriveReportsATableNamedTwiceInOneDatabase(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CHECKSUM TABLE db.t, db.t');

        self::assertInstanceOf(NonUniqueTable::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveAcceptsOneNameInTwoDatabases(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('CHECKSUM TABLE a.t, b.t')->facts->diagnostics);
    }

    public function testRenderWritesKeywordsOptionAndTables(): void
    {
        self::assertSame('OPTIMIZE NO_WRITE_TO_BINLOG TABLE a.t, t', (new Semantics(Dialect::MySql))->analyze('optimize local table a.t, t')->toString());
    }
}
