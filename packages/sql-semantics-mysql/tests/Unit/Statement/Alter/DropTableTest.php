<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DropTable::class)]
#[Medium]
final class DropTableTest extends TestCase
{
    public function testDeriveStatementResolvesEveryTable(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $drop = $semantics->diagnose('DROP TABLE t, u, t', [$table]);

        self::assertInstanceOf(DropTable::class, $drop->statement);
        self::assertInstanceOf(DeclaredTable::class, $drop->facts->relation($drop->statement->tables[0])->table);
        self::assertInstanceOf(MissingTable::class, $drop->facts->relation($drop->statement->tables[1])->table);
        self::assertInstanceOf(RepeatedTable::class, $drop->facts->diagnostics[1]);
    }

    public function testRenderWritesEveryPart(): void
    {
        self::assertSame('DROP TEMPORARY TABLE IF EXISTS t RESTRICT', (new Inspection())->diagnose('drop temporary tables if exists t restrict')->toString());
    }

    public function testDeriveStatementRefusesAViewWithoutIfExists(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $view = $semantics->diagnose('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is a view: DROP TABLE reports it as an unknown table.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('DROP TABLE v', [$table, $view])->facts->diagnostics));
        self::assertSame([], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('DROP TABLE IF EXISTS v', [$table, $view])->facts->diagnostics));
    }
}
