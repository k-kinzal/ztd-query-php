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
final class SingleRowSubqueryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['=', '<>', '<', '<=', '>', '>=', '<=>'] as $operator) {
            yield 'table ' . $operator => ['SELECT (1, 10) ' . $operator . ' (SELECT id,a FROM t1 WHERE id=1)'];
            yield 'empty ' . $operator => ['SELECT (NULL, NULL) ' . $operator . ' (SELECT id,a FROM t1 WHERE 0)'];
            yield 'constant ' . $operator => ['SELECT (1, 2) ' . $operator . ' (SELECT 1, 2)'];
            yield 'two subqueries ' . $operator => ['SELECT (SELECT 1, 2) ' . $operator . ' (SELECT 1, 2)'];
        }
        yield 'two empty subqueries' => ['SELECT (SELECT 1,2 WHERE 0) <=> (SELECT 1,2 WHERE 0)'];
        yield 'two empty table subqueries' => ['SELECT (SELECT id,a FROM t1 WHERE 0) <=> (SELECT id,a FROM t2 WHERE 0)'];
        yield 'two zero limit subqueries' => ['SELECT (SELECT 1,2 LIMIT 0) <=> (SELECT 1,2 LIMIT 0)'];
        yield 'mixed empty subqueries' => ['SELECT (SELECT 1,2 WHERE 0) <=> (SELECT id,a FROM t1 WHERE 0)'];
        yield 'empty and null subqueries' => ['SELECT (SELECT 1,2 WHERE 0) <=> (SELECT NULL,NULL)'];
        yield 'left subquery' => ['SELECT (SELECT id,a FROM t1 WHERE id=1) = (1,10)'];
        yield 'correlated' => ['SELECT id, (id,a) = (SELECT id,a FROM t2 WHERE t2.id=t1.id) FROM t1 ORDER BY id'];
        yield 'null in a row' => ['SELECT (1,NULL) = (SELECT 1,NULL), (1,NULL) <=> (SELECT 1,NULL)'];
        yield 'later mismatch after null' => ['SELECT (NULL,1) = (SELECT NULL,2)'];
        yield 'multiple rows' => ['SELECT (1,2) = (SELECT id,a FROM t1)'];
        yield 'width mismatch' => ['SELECT (1,2) = (SELECT id FROM t1)'];
        yield 'constant limit zero' => ['SELECT (1,2) = (SELECT 1,2 LIMIT 0)'];
        yield 'constructor error' => ['SELECT (1,CAST(1 AS UNSIGNED)-2) = (SELECT 2,2)'];
        yield 'conversion' => ["SELECT (1,0) = (SELECT 1,'x')"];
        yield 'charset conversion' => ['SELECT (1,_latin1 0xE9) = (SELECT 1,_utf8mb4 0xC3A9)'];
        yield 'membership left subquery' => ['SELECT (SELECT 1,10) IN (SELECT id,a FROM t1)'];
        yield 'membership empty left' => ['SELECT (SELECT 1,2 WHERE 0) IN (SELECT id,a FROM t1)'];
        yield 'membership both empty' => ['SELECT (SELECT 1,2 WHERE 0) IN (SELECT id,a FROM t1 WHERE 0)'];
        yield 'membership multiple left rows' => ['SELECT (SELECT id,a FROM t1) IN (SELECT id,a FROM t2)'];
    }

    #[DataProvider('providerStatements')]
    public function testComparisonMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
