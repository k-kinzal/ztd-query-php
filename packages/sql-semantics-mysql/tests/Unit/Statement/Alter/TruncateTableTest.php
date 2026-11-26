<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TruncateTable::class)]
#[Medium]
final class TruncateTableTest extends TestCase
{
    public function testDeriveStatementResolvesTheTable(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $truncate = $semantics->diagnose('TRUNCATE t', [$table]);

        self::assertInstanceOf(DeclaredTable::class, $truncate->facts->relation($truncate->statement)->table);
    }

    public function testRenderWritesTable(): void
    {
        self::assertSame('TRUNCATE TABLE db.t', (new Inspection('mysql-5.6.51'))->diagnose('truncate db.t')->toString());
    }

    public function testDeriveStatementRefusesAView(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $view = $semantics->diagnose('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['v is a view: TRUNCATE TABLE reports that the table doesn\'t exist.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('TRUNCATE TABLE v', [$table, $view])->facts->diagnostics));
    }
}
