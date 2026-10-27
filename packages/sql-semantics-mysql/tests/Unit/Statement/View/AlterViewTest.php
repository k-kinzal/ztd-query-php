<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\View\AlterView;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(AlterView::class)]
#[Medium]
final class AlterViewTest extends TestCase
{
    public function testValueRejectsTheCreateOnlyExistenceClause(): void
    {
        $this->expectExceptionMessage('ALTER VIEW has no IF NOT EXISTS clause.');

        new AlterView(new \SqlSemantics\Platform\MySql\Statement\View\ViewDefinition(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('v')), new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]), ifNotExists: true));
    }

    public function testDeriveStatementResolvesTheViewAndDeclaresNothing(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $view = $semantics->analyze('CREATE VIEW v AS SELECT 1 AS a');
        $alter = $semantics->analyze('ALTER VIEW v AS SELECT 2 AS b', [$view]);
        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $alter);
        $fact = $alter->relationInformation()->table;

        self::assertInstanceOf(DeclaredTable::class, $fact);
        self::assertSame($view->declarations()[0], $fact->table);
        self::assertSame([], $alter->declarations());
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('ALTER ALGORITHM = MERGE VIEW v (x) AS SELECT 1 WITH CASCADED CHECK OPTION', (new Semantics(Dialect::MySql))->analyze('ALTER ALGORITHM = MERGE VIEW v (x) AS SELECT 1 WITH CASCADED CHECK OPTION')->toString());
    }

    public function testDeriveStatementRefusesABaseTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $view = $semantics->analyze('CREATE VIEW v AS SELECT a FROM t', [$table]);

        self::assertSame(['t is not VIEW.'], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER VIEW t AS SELECT 1', [$table, $view])->diagnostics()));
        self::assertSame([], array_map(static fn ($diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER VIEW v AS SELECT 1', [$table, $view])->diagnostics()));
    }
}
