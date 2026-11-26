<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Rules\Server\MaintainedTables;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Testing\MySql\Inspection;

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
        $operation = (new Inspection())->diagnose('CHECKSUM TABLE db.t, db.t');

        self::assertInstanceOf(NonUniqueTable::class, $operation->facts->diagnostics[0]);
    }

    public function testDeriveAcceptsOneNameInTwoDatabases(): void
    {
        self::assertSame([], (new Inspection())->diagnose('CHECKSUM TABLE a.t, b.t')->facts->diagnostics);
    }

    public function testRenderWritesKeywordsOptionAndTables(): void
    {
        self::assertSame('OPTIMIZE NO_WRITE_TO_BINLOG TABLE a.t, t', (new Inspection())->diagnose('optimize local table a.t, t')->toString());
    }
}
