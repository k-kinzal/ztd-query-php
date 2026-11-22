<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior;
use SqlSemantics\Platform\MySql\Statement\View\DropView;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DropView::class)]
#[Medium]
final class DropViewTest extends TestCase
{
    public function testDeriveStatementKeepsTheWrittenBehavior(): void
    {
        $create = (new Inspection())->diagnose('DROP VIEW v CASCADE');
        $statement = $create->statement;
        self::assertInstanceOf(DropView::class, $statement);

        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame([], $create->facts->diagnostics);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('DROP VIEW IF EXISTS v, db.w RESTRICT', (new Inspection())->diagnose('DROP VIEW IF EXISTS v, db.w RESTRICT')->toString());
    }

    public function testDeriveStatementReportsTheNamesTheServerRefuses(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $view = $semantics->diagnose('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['t is not VIEW.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('DROP VIEW v, t', [$table, $view])->facts->diagnostics));
        self::assertSame(['Relation w does not exist.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('DROP VIEW w', [$table, $view])->facts->diagnostics));
        self::assertSame([], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('DROP VIEW IF EXISTS w, t', [$table, $view])->facts->diagnostics));
        self::assertSame(['t is not VIEW.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), (new Inspection('mysql-9.1.0'))->diagnose('DROP VIEW IF EXISTS t', [(new Inspection('mysql-9.1.0'))->diagnose('CREATE TABLE t (a INT)')])->facts->diagnostics));
    }
}
