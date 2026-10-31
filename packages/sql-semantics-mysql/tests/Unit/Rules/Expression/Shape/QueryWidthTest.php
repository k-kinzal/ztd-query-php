<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Shape;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryWidth;

#[CoversClass(QueryWidth::class)]
#[Medium]
final class QueryWidthTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function providerQueries(): iterable
    {
        yield 'explicit projection' => ['SELECT 1,2', 2];
        yield 'wildcard' => ['SELECT * FROM t', null];
        yield 'table' => ['TABLE t', null];
        yield 'values' => ['VALUES ROW(1,2,3)', 3];
        yield 'set' => ['(SELECT 1,2) UNION SELECT 3,4', 2];
        yield 'derived star' => ['SELECT * FROM (SELECT 1 AS a, 2 AS b) AS q', 2];
        yield 'qualified derived star' => ['SELECT q.*, 3 FROM (SELECT 1 AS a, 2 AS b) AS q', 3];
        yield 'nested qualified star' => ['SELECT q.* FROM ((SELECT 1 AS a) AS q JOIN (SELECT 2 AS b) AS r)', 1];
        yield 'comma list' => ['SELECT * FROM (SELECT 1 AS a) AS q, (SELECT 2 AS b) AS r', 2];
        yield 'natural join' => ['SELECT * FROM (SELECT 1 AS a, 2 AS b) AS q NATURAL JOIN (SELECT 3 AS b, 4 AS c) AS r', 3];
        yield 'using join' => ['SELECT * FROM (SELECT 1 AS a, 2 AS b) AS q JOIN (SELECT 3 AS b, 4 AS c) AS r USING (b)', 3];
        yield 'qualified coalesced source' => ['SELECT q.*,r.* FROM (SELECT 1 AS a) AS q NATURAL JOIN (SELECT 2 AS a) AS r', 2];
        yield 'unknown catalog member' => ['SELECT * FROM (SELECT 1 AS a) AS q JOIN t', null];
        yield 'known qualified catalog join' => ['SELECT q.* FROM (SELECT 1 AS a) AS q JOIN t', 1];
        yield 'common star' => ['WITH c AS (SELECT 1 AS a,2 AS b) SELECT * FROM c', 2];
        yield 'common dependency' => ['WITH c AS (SELECT 1 AS a),d AS (SELECT * FROM c) SELECT * FROM d', 1];
        yield 'common table query' => ['WITH c AS (SELECT 1 AS a) TABLE c', 1];
        yield 'recursive star' => ['WITH RECURSIVE c AS (SELECT 1 AS a UNION ALL SELECT a+1 FROM c WHERE a<3) SELECT * FROM c', 1];
        yield 'inner shadow' => ['WITH c AS (SELECT 1 AS a,2 AS b) SELECT * FROM (WITH c AS (SELECT 3 AS x) SELECT * FROM c) AS q', 1];
        yield 'nonrecursive shadow reads outer' => ['WITH c AS (SELECT 1 AS a,2 AS b) SELECT * FROM (WITH c AS (SELECT * FROM c) SELECT * FROM c) AS q', 2];
        yield 'common forward reference' => ['WITH c AS (SELECT * FROM d),d AS (SELECT 1 AS a) SELECT * FROM c', null];
        yield 'derived rename' => ['SELECT * FROM (SELECT * FROM t) AS q(a,b)', 2];
        yield 'common rename' => ['WITH c(a,b) AS (SELECT * FROM t) SELECT * FROM c', 2];
        yield 'json nested columns' => ['SELECT * FROM JSON_TABLE(\'[1]\', \'$[*]\' COLUMNS (a FOR ORDINALITY, NESTED PATH \'$\' COLUMNS (b INT PATH \'$\'))) j', 2];

    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerQueries')]
    public function testOfDistinguishesFixedOutputFromCatalogDependentOutput(string $sql, ?int $width): void
    {
        $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $query);
        self::assertSame($width, (new QueryWidth())->of($query->query));
    }
}
