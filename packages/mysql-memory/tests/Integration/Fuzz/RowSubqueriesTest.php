<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PDO;
use PDOStatement;
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

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerCoercion(): iterable
    {
        yield 'table coercion' => ['SELECT (0, 1) IN (SELECT b, id FROM t1)'];
        yield 'table coercion negated' => ['SELECT (0, 1) NOT IN (SELECT b, id FROM t1)'];
        yield 'table coercion later row' => ['SELECT (0, 2) IN (SELECT b, id FROM t1)'];
        yield 'table coercion unequal' => ['SELECT (1, 1) IN (SELECT b, id FROM t1)'];
        yield 'table coercion without key' => ['SELECT (0, 10) IN (SELECT b, a FROM t1)'];
        yield 'table coercion filtered' => ['SELECT (0, 1) IN (SELECT b, id FROM t1 WHERE id = 1)'];
        yield 'table coercion grouped' => ['SELECT (0, 1) IN (SELECT b, id FROM t1 GROUP BY b, id)'];
        yield 'table coercion null key' => ['SELECT (0, NULL) IN (SELECT b, id FROM t1)'];
        yield 'table coercion null value' => ['SELECT (NULL, 2) IN (SELECT b, id FROM t1)'];
        yield 'grouped later row' => ['SELECT (0, 2) IN (SELECT b, id FROM t1 GROUP BY b, id)'];
        yield 'constant across outer rows' => ['SELECT id, (0, 1) IN (SELECT b, id FROM t1) FROM t2 ORDER BY id'];
        yield 'false across outer rows' => ['SELECT id, (1, 1) IN (SELECT b, id FROM t1) FROM t2 ORDER BY id'];
        yield 'correlated outer rows' => ['SELECT id, (0, id) IN (SELECT b, id FROM t1) FROM t2 ORDER BY id'];
        yield 'empty outer rows' => ['SELECT (0, 1) IN (SELECT b, id FROM t1) FROM t2 WHERE 0'];
        yield 'zero limit' => ['SELECT (0, 1) IN (SELECT b, id FROM t1) LIMIT 0'];
        yield 'unknown with conversion' => ['SELECT (NULL, 0, 1) IN (SELECT a, b, id FROM t1)'];
        yield 'later unknown with conversion' => ['SELECT (0, NULL, 1) IN (SELECT b, a, id FROM t1)'];
        yield 'inner null with conversion' => ['SELECT (0, 0, 1) IN (SELECT b, NULL, id FROM t1)'];
        yield 'missing primary key' => ['SELECT (0, 9) IN (SELECT b, id FROM t1)'];
        foreach ([0, 1] as $value) {
            foreach ([1, 99] as $key) {
                yield 'constant first null ' . $value . ' key ' . $key => ['SELECT (0,' . $value . ',' . $key . ') IN (SELECT NULL,b,id FROM t1)'];
                yield 'constant last null ' . $value . ' key ' . $key => ['SELECT (' . $value . ',' . $key . ',0) IN (SELECT b,id,NULL FROM t1)'];
                if ($value !== 0 || $key !== 1) {
                    yield 'constant middle null ' . $value . ' key ' . $key => ['SELECT (' . $value . ',0,' . $key . ') IN (SELECT b,NULL,id FROM t1)'];
                }
            }
        }
    }

    #[DataProvider('providerCoercion')]
    public function testCoercionWarningsMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);
        $native = new PDO($target->native . ';dbname=fz', $target->nativeUser, $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $statement = $native->query('EXPLAIN FORMAT=JSON ' . $sql);
        self::assertInstanceOf(PDOStatement::class, $statement);
        $plan = $statement->fetchAll(PDO::FETCH_NUM);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference . "\nNative plan: " . var_export($plan, true));
    }
}
