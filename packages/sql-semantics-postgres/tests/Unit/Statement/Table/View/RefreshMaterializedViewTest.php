<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\RefreshMaterializedView::class)]
#[Medium]
final class RefreshMaterializedViewTest extends TestCase
{
    public function testDeriveStatementResolvesTheView(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('REFRESH MATERIALIZED VIEW t', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\RefreshMaterializedView::class, $n1);
        $n2 = $statement->facts->relation($n1)->table;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable::class, $n2);
        self::assertSame(true, $n2->table === $context[0]);
    }

    public function testDeriveRelationResolvesTheView(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->diagnose('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->diagnose('REFRESH MATERIALIZED VIEW t', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\RefreshMaterializedView::class, $n1);
        self::assertSame(3, count($n1->deriveRelation(new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation($statement->context), new \SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->diagnose('REFRESH MATERIALIZED VIEW CONCURRENTLY s.m WITH NO DATA', []);
        self::assertSame('REFRESH MATERIALIZED VIEW CONCURRENTLY s.m WITH NO DATA', $statement->toString());
    }

    public function testDeriveStatementReportsAnotherKind(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [$semantics->diagnose('CREATE TABLE t (a int)'), $semantics->diagnose('CREATE VIEW v AS SELECT 1 AS a'), $semantics->diagnose('CREATE MATERIALIZED VIEW m AS SELECT 1 AS a')];
        self::assertSame(['"t" is not a materialized view'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('REFRESH MATERIALIZED VIEW t', $context)->facts->diagnostics));
        self::assertSame(['"v" is not a table or materialized view'], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('REFRESH MATERIALIZED VIEW v', $context)->facts->diagnostics));
        self::assertSame([], array_map(static fn ($problem): string => $problem->message(), $semantics->diagnose('REFRESH MATERIALIZED VIEW m', $context)->facts->diagnostics));
    }
}
