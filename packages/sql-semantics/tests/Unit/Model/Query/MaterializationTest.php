<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Materialization;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Materialization::class)]
#[Medium]
final class MaterializationTest extends TestCase
{
    public function testRepresentsEveryMaterializationPolicy(): void
    {
        self::assertSame(['default', 'materialized', 'not-materialized'], array_column(Materialization::cases(), 'value'));
    }

    #[TestWith(['WITH q AS MATERIALIZED (SELECT 1 AS n) SELECT n FROM q', Materialization::Materialized, 'WITH "q" AS MATERIALIZED(SELECT 1 AS "n") SELECT "n" AS "n" FROM "q"'])]
    #[TestWith(['WITH q AS NOT MATERIALIZED (SELECT 1 AS n) SELECT n FROM q', Materialization::Inline, 'WITH "q" AS NOT MATERIALIZED(SELECT 1 AS "n") SELECT "n" AS "n" FROM "q"'])]
    #[TestWith(['WITH q AS (SELECT 1 AS n) SELECT n FROM q', Materialization::Default, 'WITH "q" AS (SELECT 1 AS "n") SELECT "n" AS "n" FROM "q"'])]
    public function testBindsThePolicyAndWritesItBack(string $sql, Materialization $materialization, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertNotNull($statement->ctes);
        self::assertSame($materialization, $statement->ctes->definitions[0]->materialization);
        self::assertSame($expected, $statement->toString());
    }
}
