<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
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
        $drop = $semantics->analyze('DROP TABLE t, u', [$table]);

        self::assertInstanceOf(DropTable::class, $drop);
        self::assertInstanceOf(DeclaredTable::class, $drop->tables[0]->relationInformation()->table);
        self::assertInstanceOf(MissingTable::class, $drop->tables[1]->relationInformation()->table);
    }

    public function testRenderWritesEveryPart(): void
    {
        self::assertSame('DROP TEMPORARY TABLES IF EXISTS t RESTRICT', (new Semantics(Dialect::MySql))->analyze('drop temporary tables if exists t restrict')->toString());
    }

    public function testRenderRequiresDistinctTableIdentities(): void
    {
        $this->expectExceptionMessage('DROP TABLE cannot repeat an identical table name.');
        new DropTable(false, false, [
            new \SqlSemantics\Platform\MySql\Statement\Alter\TargetTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))),
            new \SqlSemantics\Platform\MySql\Statement\Alter\TargetTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))),
        ]);
    }

    public function testDeriveStatementRefusesAViewWithoutIfExists(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is a view: DROP TABLE reports it as an unknown table.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('DROP TABLE v', [$table, $view])->diagnostics()));
        self::assertSame([], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('DROP TABLE IF EXISTS v', [$table, $view])->diagnostics()));
    }
}
