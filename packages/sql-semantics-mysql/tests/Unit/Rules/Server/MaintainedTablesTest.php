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

    public function testCheckedRefusesRepeatedIdentitiesBeforeDerivation(): void
    {
        $this->expectExceptionMessage('A maintenance statement cannot repeat an identical table name.');
        (new MaintainedTables())->checked([new MaintainedTable(new QualifiedName(new Name('t'))), new MaintainedTable(new QualifiedName(new Name('t')))]);
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
