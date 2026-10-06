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
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('REFRESH MATERIALIZED VIEW t', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\RefreshMaterializedView::class, $n1);
        $n2 = $statement->facts->relation($n1)->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\DeclaredTable::class, $n2);
        self::assertSame(true, $n2->table === $context[0]);
    }

    public function testDeriveRelationResolvesTheView(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('REFRESH MATERIALIZED VIEW t', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\RefreshMaterializedView::class, $n1);
        self::assertSame(3, count($n1->deriveRelation(new \SqlSemantics\Construction\Derivation($statement->context), new \SqlSemantics\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('REFRESH MATERIALIZED VIEW CONCURRENTLY s.m WITH NO DATA', []);
        self::assertSame('REFRESH MATERIALIZED VIEW CONCURRENTLY s.m WITH NO DATA', $statement->toString());
    }

    public function testDeriveStatementReportsAnotherKind(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [$semantics->analyze('CREATE TABLE t (a int)'), $semantics->analyze('CREATE VIEW v AS SELECT 1 AS a'), $semantics->analyze('CREATE MATERIALIZED VIEW m AS SELECT 1 AS a')];
        self::assertSame(['"t" is not a materialized view'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('REFRESH MATERIALIZED VIEW t', $context)->facts->diagnostics));
        self::assertSame(['"v" is not a table or materialized view'], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('REFRESH MATERIALIZED VIEW v', $context)->facts->diagnostics));
        self::assertSame([], array_map(static fn ($problem): string => $problem->message(), $semantics->analyze('REFRESH MATERIALIZED VIEW m', $context)->facts->diagnostics));
    }
}
