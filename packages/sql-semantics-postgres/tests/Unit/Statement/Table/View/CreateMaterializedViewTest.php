<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateMaterializedView::class)]
#[Medium]
final class CreateMaterializedViewTest extends TestCase
{
    public function testDeriveStatementDeclaresTheViewWithTheNullFactsOfTheQuery(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE MATERIALIZED VIEW m AS SELECT a, b FROM t', $context);
        self::assertSame([
          0 => 'a integer NotNull',
          1 => 'b integer Nullable',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE MATERIALIZED VIEW IF NOT EXISTS m (x) USING heap WITH (fillfactor = 70) TABLESPACE s AS SELECT 1 WITH DATA', []);
        self::assertSame('CREATE MATERIALIZED VIEW IF NOT EXISTS m (x) USING heap WITH (fillfactor = 70) TABLESPACE s AS SELECT 1 WITH DATA', $statement->toString());
    }

    public function testRejectsWithDataAfterAnOpenIsJsonTest(): void
    {
        $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT a FROM t ORDER BY b IS JSON')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $query);
        $this->expectExceptionMessage('A query ending in IS JSON without a uniqueness clause would take WITH DATA; group it.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateMaterializedView(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('m')), $query, withData: true);
    }
}
