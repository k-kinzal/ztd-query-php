<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTableAs::class)]
#[Medium]
final class CreateTableAsTest extends TestCase
{
    public function testDeriveStatementDeclaresTheColumnsOfTheQuery(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TABLE n (x) AS SELECT a, \'v\' AS s FROM t', $context);
        self::assertSame([
          0 => 'x integer Nullable',
          1 => 's text Nullable',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TEMP TABLE IF NOT EXISTS n (x) USING heap WITH (fillfactor = 70) ON COMMIT DROP TABLESPACE s AS SELECT 1 WITH NO DATA', []);
        self::assertSame('CREATE TEMP TABLE IF NOT EXISTS n (x) USING heap WITH (fillfactor = 70) ON COMMIT DROP TABLESPACE s AS SELECT 1 WITH NO DATA', $statement->toString());
    }

    public function testRejectsWithDataAfterAnOpenIsJsonTest(): void
    {
        $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT a IS JSON')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $query);
        $this->expectExceptionMessage('A query ending in IS JSON without a uniqueness clause would take WITH DATA; group it.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTableAs(new \SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('n'))), $query, withData: false);
    }
}
