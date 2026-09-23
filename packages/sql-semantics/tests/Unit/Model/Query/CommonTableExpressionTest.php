<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\CommonTableExpression;
use SqlSemantics\Model\Query\Materialization;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CommonTableExpression::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class CommonTableExpressionTest extends TestCase
{
    public function testRequiresAnInputWithAQueryOrWriteShape(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 AS id, 2 AS value');
        self::assertInstanceOf(BoundSelect::class, $query);
        $cte = new CommonTableExpression('q', $query, ['renamed'], Materialization::Materialized);
        self::assertSame($query, $cte->query);
        self::assertSame(['renamed'], $cte->columns);
        self::assertSame(Materialization::Materialized, $cte->materialization);
    }

    public function testRejectsMoreAliasesThanResultColumns(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        new CommonTableExpression('q', $query, ['a', 'b']);
    }

    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testRequiresAllResultAliasesOutsidePostgresql(Dialect $dialect): void
    {
        $query = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1, 2');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        new CommonTableExpression('q', $query, ['a']);
    }

    public function testRejectsMysqlMaterializationClauses(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        new CommonTableExpression('q', $query, materialization: Materialization::Inline);
    }
}
