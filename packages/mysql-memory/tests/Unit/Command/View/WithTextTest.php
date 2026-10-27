<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\ViewText;
use MySqlMemory\Command\View\WithText;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;

#[CoversClass(WithText::class)]
#[Small]
final class WithTextTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, list<int>}>
     */
    public static function providerClauses(): iterable
    {
        yield 'unused last' => ['WITH a AS (SELECT 1 AS x), b AS (TABLE a) TABLE a', 'with `a` as (select 1 AS `x`),  ', [0]];
        yield 'used last' => ['WITH a AS (SELECT 1 AS x), b AS (TABLE a) TABLE b', 'with `a` as (select 1 AS `x`), `b` as (select `a`.`x` AS `x` from `a`) ', [0, 1]];
        yield 'unused first' => ['WITH a AS (SELECT 1 AS x), b AS (SELECT 2 AS x) TABLE b', 'with `b` as (select 2 AS `x`) ', [1]];
        yield 'all unused' => ['WITH a AS (SELECT 1 AS x) SELECT 2', '', []];
    }

    /**
     * @param list<int> $positions The reached declaration positions
     */
    #[DataProvider('providerClauses')]
    public function testClauseAndUsedRetainWrittenPositions(string $sql, string $expected, array $positions): void
    {
        $operation = (new Instance())->connect()->analyze($sql);
        self::assertInstanceOf(QueryRoot::class, $operation);
        $query = $operation->query;
        self::assertInstanceOf(QueryExpression::class, $query);
        $writer = new WithText(new ViewText($operation->statementInformation(), '', ''));
        self::assertSame($expected, $writer->clause($query));
        self::assertSame($positions, array_keys($writer->used($query)));
    }

    public function testUsedReturnsNoDefinitionsWithoutAWithClause(): void
    {
        $operation = (new Instance())->connect()->analyze('SELECT 1 UNION SELECT 2 ORDER BY 1');
        self::assertInstanceOf(QueryRoot::class, $operation);
        self::assertInstanceOf(QueryExpression::class, $operation->query);
        $writer = new WithText(new ViewText($operation->statementInformation(), '', ''));
        self::assertSame([], $writer->used($operation->query));
    }
}
