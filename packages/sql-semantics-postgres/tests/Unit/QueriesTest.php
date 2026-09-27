<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Queries;
use SqlSemantics\Statement\Writer;
use Tests\Contract\Composed;

#[CoversClass(Queries::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Builder::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\PostgreSql\Expressions::class)]
#[Medium]
final class QueriesTest extends TestCase
{
    public function testUnionAllParenthesizesAnythingButAPlainSelect(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id')->command, $semantics->analyze('SELECT 2;')->command);
        self::assertSame('SELECT 1 AS id UNION ALL SELECT 2', Writer::render($rows));
        $ordered = $builder->unionAll($rows, $semantics->analyze('SELECT 3 ORDER BY 1 LIMIT 1')->command);
        self::assertSame('SELECT 1 AS id UNION ALL SELECT 2 UNION ALL( SELECT 3 ORDER BY 1 LIMIT 1 )', Writer::render($ordered));
        self::assertSame(Writer::render($ordered), $semantics->analyze(Writer::render($ordered))->toString());
        Composed::assertQueryRoundTrips($semantics, $rows);
        self::assertSame('SELECT 1 UNION ALL( WITH w AS( SELECT 2 ) SELECT 3 )', Writer::render($builder->unionAll($semantics->analyze('SELECT 1')->command, $semantics->analyze('WITH w AS (SELECT 2) SELECT 3')->command)));
    }

    public function testCteNamesAPreparableStatementWithOptionalColumns(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        self::assertSame('users ( id , n ) AS( SELECT 1 AS id , 2 AS n )', Writer::render($builder->cte('users', $semantics->analyze('SELECT 1 AS id, 2 AS n')->command, ['id', 'n'])));
        self::assertSame('"user" AS( DELETE FROM t RETURNING id )', Writer::render($builder->cte('user', $semantics->analyze('DELETE FROM t RETURNING id')->command)));
        $sql = 'WITH ' . Writer::render($builder->cte('users', $semantics->analyze('SELECT 1 AS id, 2 AS n')->command, ['id', 'n'])) . ' SELECT 1';
        self::assertSame($sql, $semantics->analyze($sql)->toString());
        $this->expectException(CompositionException::class);
        $builder->cte('c', $semantics->analyze('CREATE TABLE t (id int)')->command);
    }

    public function testWithPrependsExpressionsToEveryShapeOfSelect(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $builder = $semantics->builder();
        $rows = $builder->unionAll($semantics->analyze('SELECT 1 AS id')->command, $semantics->analyze('SELECT 2')->command);
        $cte = $builder->cte('v', $rows);
        $shapes = [
            'SELECT id FROM users' => 'WITH v AS( SELECT 1 AS id UNION ALL SELECT 2 ) SELECT id FROM users',
            'SELECT id FROM users ORDER BY id' => 'WITH v AS( SELECT 1 AS id UNION ALL SELECT 2 ) SELECT id FROM users ORDER BY id',
            'SELECT id FROM users LIMIT 2' => 'WITH v AS( SELECT 1 AS id UNION ALL SELECT 2 ) SELECT id FROM users LIMIT 2',
            'SELECT id FROM users FOR UPDATE' => 'WITH v AS( SELECT 1 AS id UNION ALL SELECT 2 ) SELECT id FROM users FOR UPDATE',
            '(SELECT id FROM users)' => 'WITH v AS( SELECT 1 AS id UNION ALL SELECT 2 )( SELECT id FROM users )',
            'WITH RECURSIVE w AS (SELECT 3) SELECT id FROM users ORDER BY id LIMIT 1 FOR UPDATE' => 'WITH RECURSIVE v AS( SELECT 1 AS id UNION ALL SELECT 2 ) , w AS( SELECT 3 ) SELECT id FROM users ORDER BY id LIMIT 1 FOR UPDATE',
            'WITH w AS (SELECT 3) SELECT id FROM users' => 'WITH v AS( SELECT 1 AS id UNION ALL SELECT 2 ) , w AS( SELECT 3 ) SELECT id FROM users',
        ];
        self::assertSame(array_values($shapes), array_map(static fn (string $sql): string => Writer::render($builder->with([$cte], $semantics->analyze($sql)->command)), array_keys($shapes)));
        self::assertSame(array_values($shapes), array_map(static fn (string $sql): string => $semantics->analyze($sql)->toString(), array_values($shapes)));
        Composed::assertQueryRoundTrips($semantics, $builder->with([$cte], $semantics->analyze('SELECT id FROM users')->command));
    }

    public function testWithNeedsExpressionsAndASelect(): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
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
}
