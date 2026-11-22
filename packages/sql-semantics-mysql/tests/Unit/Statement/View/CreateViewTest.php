<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateView::class)]
#[Medium]
final class CreateViewTest extends TestCase
{
    public function testDeriveStatementDeclaresTheView(): void
    {
        $create = (new Inspection())->diagnose('CREATE VIEW v AS SELECT 1 AS a, NULL AS b');
        $table = $create->declarations()[0];

        self::assertSame('a', $table->columns[0]->name->value);
        self::assertFalse($table->complete);
    }

    public function testDeriveStatementReportsTheProblems(): void
    {
        $create = (new Inspection())->diagnose('CREATE VIEW v (a, b) AS SELECT 1');
        $duplicate = (new Inspection())->diagnose('CREATE VIEW v (a, a) AS SELECT 1, 2');

        self::assertInstanceOf(ViewColumnCount::class, $create->facts->diagnostics[0]);
        self::assertInstanceOf(DuplicateColumn::class, $duplicate->facts->diagnostics[0]);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('CREATE OR REPLACE VIEW v AS SELECT 1 AS a WITH CHECK OPTION', (new Inspection())->diagnose('CREATE OR REPLACE VIEW v AS SELECT 1 AS a WITH CHECK OPTION')->toString());
    }

    public function testDeriveStatementReplacesOnlyAView(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $view = $semantics->diagnose('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['t is not VIEW.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('CREATE OR REPLACE VIEW t AS SELECT 1', [$table, $view])->facts->diagnostics));
        self::assertSame([], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->diagnose('CREATE OR REPLACE VIEW v AS SELECT 1', [$table, $view])->facts->diagnostics));
        self::assertSame(RelationKind::View, $view->declarations()[0]->kind);
    }
}
