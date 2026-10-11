<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class RowSubqueriesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerMembership(): iterable
    {
        foreach (['IN', 'NOT IN', '= ANY', '= SOME', '<> ALL'] as $operator) {
            yield 'matching ' . $operator => ['SELECT (1, 10) ' . $operator . ' (SELECT id, a FROM t1)'];
            yield 'unknown ' . $operator => ['SELECT (1, NULL) ' . $operator . ' (SELECT id, a FROM t1)'];
            yield 'unequal despite null ' . $operator => ['SELECT (8, NULL) ' . $operator . ' (SELECT id, a FROM t1)'];
            yield 'empty ' . $operator => ['SELECT (NULL, NULL) ' . $operator . ' (SELECT id, a FROM t1 WHERE 0)'];
        }
        yield 'constant row' => ['SELECT (1, 10) IN (SELECT 1, 10)'];
        yield 'constant coercion' => ["SELECT (0, 1) IN (SELECT 'x', 1)"];
        yield 'table coercion' => ['SELECT (0, 1) IN (SELECT b, id FROM t1)'];
        yield 'collation' => ["SELECT (1, 'APPLE') IN (SELECT id, b FROM t1)"];
        yield 'correlated' => ['SELECT id, (a, b) IN (SELECT a, b FROM t2 WHERE t2.id <= t1.id) FROM t1 ORDER BY id'];
        yield 'true after unknown' => ['SELECT (1, 2) IN (SELECT 1, NULL UNION ALL SELECT 1, 2)'];
        yield 'unknown after false' => ['SELECT (1, 2) IN (SELECT 3, NULL UNION ALL SELECT 1, NULL)'];
        yield 'too few columns' => ['SELECT (1, 2) IN (SELECT id FROM t1)'];
        yield 'too many columns' => ['SELECT (1, 2) IN (SELECT id, a, b FROM t1)'];
        yield 'unsupported all equality' => ['SELECT (1, 10) = ALL (SELECT id, a FROM t1)'];
        yield 'unsupported any ordering' => ['SELECT (1, 10) < ANY (SELECT id, a FROM t1)'];
        yield 'unsupported limit' => ['SELECT (1, 10) IN (SELECT id, a FROM t1 LIMIT 1)'];
        yield 'scalar limit' => ['SELECT 1 IN (SELECT 1 LIMIT 0)'];
        yield 'quantified limit' => ['SELECT 1 < ALL (SELECT 2 LIMIT 1)'];
        yield 'set limit' => ['SELECT 1 IN (SELECT 1 UNION SELECT 2 LIMIT 1)'];
        yield 'left branch limit' => ['SELECT 1 IN ((SELECT 1 LIMIT 1) UNION SELECT 2)'];
        yield 'right branch limit' => ['SELECT 1 IN (SELECT 1 UNION (SELECT 2 LIMIT 1))'];
        yield 'legacy late limit' => ['SELECT 1 IN (SELECT 1 FOR UPDATE LIMIT 1)'];
        yield 'derived limit permitted' => ['SELECT (1, 2) IN (SELECT * FROM (SELECT 1 a, 2 b LIMIT 1) d)'];
        yield 'common table limit permitted' => ['SELECT (1, 2) IN (WITH c AS (SELECT 1 a, 2 b LIMIT 1) SELECT * FROM c)'];
    }

    #[DataProvider('providerMembership')]
    public function testMembershipMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
