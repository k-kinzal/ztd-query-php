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
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([]));
        (new MaintainedTables())->derive($derivation, [new MaintainedTable(new QualifiedName(new Name('t'), new Name('db'))), new MaintainedTable(new QualifiedName(new Name('t'), new Name('db')))]);

        self::assertCount(1, array_filter($derivation->facts()->diagnostics, static fn ($diagnostic): bool => $diagnostic instanceof NonUniqueTable));
    }

    public function testCheckedRetainsDistinctTargetsInOrder(): void
    {
        $tables = [new MaintainedTable(new QualifiedName(new Name('t'))), new MaintainedTable(new QualifiedName(new Name('t'), new Name('d'))), new MaintainedTable(new QualifiedName(new Name('later')))];

        self::assertSame($tables, (new MaintainedTables())->checked($tables));
    }

    public function testDeriveReportsAWrittenDatabaseThatNamesTheCurrentOne(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $statement = $semantics->analyze('ANALYZE TABLE t, pbt.t, later', $semantics->context(searchPath: new \SqlSemantics\Contract\SearchPath('pbt')));
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable::class, $statement);
        $duplicates = array_values(array_filter($statement->diagnostics(), static fn ($diagnostic): bool => $diagnostic instanceof NonUniqueTable));

        self::assertCount(1, $duplicates);
        self::assertSame($statement->tables[1], $duplicates[0]->relation);
    }

    public function testDeriveAcceptsOneNameInTwoDatabases(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('CHECKSUM TABLE a.t, b.t')->diagnostics());
    }

    public function testRenderWritesKeywordsOptionAndTables(): void
    {
        self::assertSame('OPTIMIZE LOCAL TABLE a.t, t', (new Semantics(Dialect::MySql))->analyze('optimize local table a.t, t')->toString());
    }
}
