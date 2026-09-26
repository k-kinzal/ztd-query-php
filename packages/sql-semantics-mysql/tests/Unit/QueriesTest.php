<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Queries;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Composed;

#[CoversClass(Queries::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Builder::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\LegacyUnions::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\MySql\Expressions::class)]
#[Medium]
final class QueriesTest extends TestCase
{
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-8.0.44'])]
    public function testUnionAllParenthesizesOrderedOperandsAndNestsFlat(string $version): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $builder = $semantics->builder();
        $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id')->command, $semantics->analyze('SELECT 2')->command);
        self::assertSame('SELECT 1 AS id UNION ALL SELECT 2', Writer::render($rows));
        $ordered = $builder->unionAll($rows, $semantics->analyze('SELECT 3 ORDER BY 1 LIMIT 1')->command);
        self::assertSame('SELECT 1 AS id UNION ALL SELECT 2 UNION ALL( SELECT 3 ORDER BY 1 LIMIT 1 )', Writer::render($ordered));
        self::assertSame('SELECT 1 AS id UNION ALL SELECT 2 UNION ALL( SELECT 3 FOR UPDATE )', Writer::render($builder->unionAll($rows, $semantics->analyze('SELECT 3 FOR UPDATE')->command)));
        self::assertSame(Writer::render($ordered), $semantics->analyze(Writer::render($ordered))->toString());
        Composed::assertQueryRoundTrips($semantics, $rows);
    }

    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-8.0.44'])]
    public function testCteNamesAQueryWithOptionalColumns(string $version): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $builder = $semantics->builder();
        $rows = $semantics->analyze('SELECT 1 AS id, 2 AS n')->command;
        self::assertSame('users ( id , n ) AS( SELECT 1 AS id , 2 AS n )', Writer::render($builder->cte('users', $rows, ['id', 'n'])));
        self::assertSame('`select` AS( SELECT 1 AS id , 2 AS n )', Writer::render($builder->cte('select', $rows)));
        self::assertSame('c AS( SELECT 1 FOR UPDATE )', Writer::render($builder->cte('c', $semantics->analyze('SELECT 1 FOR UPDATE')->command)));
        $sql = 'WITH ' . Writer::render($builder->cte('users', $rows, ['id', 'n'])) . ' SELECT 1';
        self::assertSame($sql, $semantics->analyze($sql)->toString());
    }

    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-8.0.44'])]
    public function testWithPrependsExpressionsToAQueryAndKeepsExistingOnes(string $version): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $builder = $semantics->builder();
        $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id')->command, $semantics->analyze('SELECT 2')->command);
        $query = $builder->with([$builder->cte('users', $rows, ['id'])], $semantics->analyze('SELECT id FROM users')->command);
        self::assertSame('WITH users ( id ) AS( SELECT 1 AS id UNION ALL SELECT 2 ) SELECT id FROM users', Writer::render($query));
        self::assertSame(Writer::render($query), $semantics->analyze(Writer::render($query))->toString());
        Composed::assertQueryRoundTrips($semantics, $query);
        $merged = $builder->with([$builder->cte('v', $rows)], $semantics->analyze('WITH RECURSIVE w AS (SELECT 3) SELECT id FROM users ORDER BY id LIMIT 1 FOR UPDATE')->command);
        self::assertSame('WITH RECURSIVE v AS( SELECT 1 AS id UNION ALL SELECT 2 ) , w AS( SELECT 3 ) SELECT id FROM users ORDER BY id LIMIT 1 FOR UPDATE', Writer::render($merged));
        self::assertSame(Writer::render($merged), $semantics->analyze(Writer::render($merged))->toString());
    }

    public function testWithNeedsExpressionsAndAQueryExpression(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $builder = $semantics->builder();
        $cte = $builder->cte('c', $semantics->analyze('SELECT 1')->command);
        try {
            $builder->with([], $semantics->analyze('SELECT 1')->command);
            self::fail('An empty WITH clause must be rejected.');
        } catch (CompositionException $error) {
            self::assertStringContainsString('at least one', $error->getMessage());
        }
        $this->expectException(CompositionException::class);
        $builder->with([$cte], $semantics->analyze('DELETE FROM t')->command);
    }

    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-5.6.51'])]
    public function testCteAndWithHaveNoFormBefore80(string $version): void
    {
        $semantics = new Semantics(Dialect::MySql, $version);
        $builder = $semantics->builder();
        try {
            $builder->cte('c', $semantics->analyze('SELECT 1')->command);
            self::fail('No common table expression before 8.0.');
        } catch (CompositionException $error) {
            self::assertStringContainsString($version, $error->getMessage());
        }
        $this->expectException(CompositionException::class);
        $builder->with([$semantics->analyze('SELECT 1')->command], $semantics->analyze('SELECT 2')->command);
    }
}
