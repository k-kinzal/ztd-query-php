<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\Recursion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Recursion\CycleClause;
use SqlSemantics\Model\Query\Recursion\SearchClause;
use SqlSemantics\Model\Query\Recursion\SearchOrder;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Query\Recursion\RecursionClauses::class)]
#[Medium]
final class RecursionClausesTest extends TestCase
{
    public function testSearchReadsTheOrderColumnsAndSequenceColumn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH RECURSIVE r(a, b) AS (SELECT 1, 2 UNION ALL SELECT a, b FROM r) SEARCH DEPTH FIRST BY a, b SET ord SELECT a FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $search = $statement->ctes?->definitions[0]->search;
        self::assertInstanceOf(SearchClause::class, $search);
        self::assertSame([SearchOrder::DepthFirst, ['a', 'b'], 'ord'], [$search->order, $search->columns, $search->sequenceColumn]);
    }

    public function testCycleReadsTheMarkValues(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH RECURSIVE r(a) AS (SELECT 1 UNION ALL SELECT a FROM r) CYCLE a SET c TO 1 DEFAULT 0 USING p SELECT a FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $cycle = $statement->ctes?->definitions[0]->cycle;
        self::assertInstanceOf(CycleClause::class, $cycle);
        self::assertSame(['1', '0'], [$cycle->markValue?->structure()->toString(), $cycle->markDefault?->structure()->toString()]);
    }

    public function testListedReadsEveryListedColumn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH RECURSIVE r(a, b) AS (SELECT 1, 2 UNION ALL SELECT a, b FROM r) CYCLE a, b SET c USING p SELECT a FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame(['a', 'b'], $statement->ctes?->definitions[0]->cycle?->columns);
    }

    public function testNamesReadTheAddedColumnsInWrittenOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH RECURSIVE r(a) AS (SELECT 1 UNION ALL SELECT a FROM r) CYCLE a SET "Mark" USING route SELECT a FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame(['Mark', 'route'], [$statement->ctes?->definitions[0]->cycle?->markColumn, $statement->ctes?->definitions[0]->cycle?->pathColumn]);
    }

    public function testColumnsTypeTheAddedColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH RECURSIVE r(a) AS (SELECT 1 UNION ALL SELECT a FROM r) SEARCH BREADTH FIRST BY a SET s CYCLE a SET c USING p SELECT * FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame(['a' => 'integer', 's' => 'record', 'c' => 'boolean', 'p' => 'record[]'], array_combine(array_map(static fn ($output): string => (string) $output->name, $statement->outputs), array_map(static fn ($output): string => $output->expression->type->name, $statement->outputs)));
    }

    public function testDeclarationKeepsAPlainDefinitionUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH r(a) AS (SELECT 1) SELECT * FROM r');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame(['a'], array_map(static fn ($output): ?string => $output->name, $statement->outputs));
    }
}
