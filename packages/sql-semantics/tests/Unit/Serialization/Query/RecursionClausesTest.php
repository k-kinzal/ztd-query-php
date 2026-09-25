<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\CommonTableExpression;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Query\RecursionClauses::class)]
#[Medium]
final class RecursionClausesTest extends TestCase
{
    public function testWriteSpellsSearchBeforeCycle(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH RECURSIVE r(a) AS (SELECT 1 UNION ALL SELECT a FROM r) SEARCH DEPTH FIRST BY a SET s CYCLE a SET c TO TRUE DEFAULT FALSE USING p SELECT a FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $definition = $statement->ctes?->definitions[0];
        self::assertInstanceOf(CommonTableExpression::class, $definition);
        self::assertSame(['SEARCH DEPTH FIRST BY "a" SET "s"', 'CYCLE "a" SET "c" TO TRUE DEFAULT FALSE USING "p"'], array_map(static fn ($tree): string => $tree->toString(), \SqlSemantics\Serialization\Query\RecursionClauses::write($definition, Dialect::PostgreSql)));
    }

    public function testWriteIsEmptyWithoutClauses(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH r(a) AS (SELECT 1) SELECT a FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $definition = $statement->ctes?->definitions[0];
        self::assertInstanceOf(CommonTableExpression::class, $definition);
        self::assertSame([], \SqlSemantics\Serialization\Query\RecursionClauses::write($definition, Dialect::PostgreSql));
    }

    public function testNamesQuotesEachColumn(): void
    {
        self::assertSame('"a", "B"', \SqlSemantics\Serialization\Query\RecursionClauses::names(['a', 'B'], Dialect::PostgreSql)->toString());
    }
}
