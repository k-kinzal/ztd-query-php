<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\View\AlterView;

#[CoversClass(AlterView::class)]
#[Medium]
final class AlterViewTest extends TestCase
{
    public function testDeriveStatementResolvesTheViewAndDeclaresNothing(): void
    {
        $semantics = new Semantics();
        $view = $semantics->diagnose('CREATE VIEW v AS SELECT 1 AS a');
        $alter = $semantics->diagnose('ALTER VIEW v AS SELECT 2 AS b', [$view]);
        $fact = $alter->facts->relation($alter->statement)->table;

        self::assertInstanceOf(DeclaredTable::class, $fact);
        self::assertSame($view->declarations()[0], $fact->table);
        self::assertSame([], $alter->declarations());
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('ALTER ALGORITHM = MERGE VIEW v (x) AS SELECT 1 WITH CASCADED CHECK OPTION', (new Semantics())->diagnose('ALTER ALGORITHM = MERGE VIEW v (x) AS SELECT 1 WITH CASCADED CHECK OPTION')->toString());
    }

    public function testDeriveStatementRefusesABaseTable(): void
    {
        $semantics = new Semantics();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $view = $semantics->diagnose('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['t is not VIEW.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('ALTER VIEW t AS SELECT 1', [$table, $view])->facts->diagnostics));
        self::assertSame([], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('ALTER VIEW v AS SELECT 1', [$table, $view])->facts->diagnostics));
    }
}
