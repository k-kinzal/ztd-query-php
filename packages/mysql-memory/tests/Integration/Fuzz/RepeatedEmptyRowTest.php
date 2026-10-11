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
final class RepeatedEmptyRowTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['NULL', '0', '1', '1+0', '1.0', '1e0', "'1'", "'x'", 'SQRT(1)', 'SQRT(-1)', '1/0', 'NULL+1', 'CONCAT(NULL)', "CONCAT('x')", "CONCAT('1')", "CAST('x' AS SIGNED)", 'CAST(1 AS CHAR)', 'CAST(1 AS DECIMAL(3,1))', 'CAST(NULL AS SIGNED)', 'CAST(NULL AS CHAR)', 'CAST(NULL AS DECIMAL(3,1))', 'IFNULL(NULL,1)', 'IFNULL(NULL,1.0)', 'COALESCE(NULL,1)', 'IF(1,1,2)', 'NULLIF(1,2)', 'ABS(1)', "STR_TO_DATE('x','%Y')"] as $expression) {
            yield $expression => ['SELECT id, (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n, (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0) <=> (0,NULL) AS z, (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0) <=> (1,NULL) AS one FROM t2 ORDER BY id'];
        }
        yield 'reversed operands' => ['SELECT id, (NULL,NULL) <=> (SELECT 1,NULL FROM DUAL WHERE 0) AS n, (1,NULL) <=> (SELECT 1,NULL FROM DUAL WHERE 0) AS one FROM t2 ORDER BY id'];
        yield 'later column' => ['SELECT id, (SELECT NULL,1 FROM DUAL WHERE 0) <=> (NULL,NULL) AS n, (SELECT NULL,1 FROM DUAL WHERE 0) <=> (NULL,1) AS one FROM t2 ORDER BY id'];
        yield 'dependent empty projection' => ['SELECT id, (SELECT t2.id,NULL FROM DUAL WHERE 0) <=> (t2.id,NULL) AS same FROM t2 ORDER BY id'];
        yield 'assigned empty projection' => ['SET @v=0; SELECT id, (SELECT @v:=@v+1,NULL FROM DUAL WHERE 0) <=> (1,NULL) AS one, @v FROM t2 ORDER BY id'];
        yield 'variable empty projection' => ['SET @v=1; SELECT id, (SELECT @v,NULL FROM DUAL WHERE 0) <=> (1,NULL) AS one FROM t2 ORDER BY id'];
        yield 'nullable outer column' => ['SELECT id,(SELECT t1.a,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n FROM t1 ORDER BY id'];
        yield 'nullable outer expression' => ['SELECT id,(SELECT t1.a+1,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n FROM t1 ORDER BY id'];
        yield 'outer value under limit' => ['SELECT id,(SELECT t1.a,NULL LIMIT 0) <=> (NULL,NULL) AS n FROM t1 ORDER BY id'];
        yield 'outer value under having' => ['SELECT id,(SELECT t1.a,NULL FROM DUAL HAVING 0) <=> (NULL,NULL) AS n FROM t1 ORDER BY id'];
        yield 'outer value two levels away' => ['SELECT id,(SELECT (SELECT t1.a,NULL FROM DUAL WHERE 0) <=> (NULL,NULL)) AS n FROM t1 ORDER BY id'];
        yield 'outer alias' => ['SELECT a AS v,(SELECT v,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n FROM t1 ORDER BY id'];
        yield 'outer alias expression' => ['SELECT a+1 AS v,(SELECT v,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n FROM t1 ORDER BY id'];
        yield 'outer value and buffered table' => ['SELECT id,(SELECT t1.a,NULL FROM t2 WHERE 0) <=> (NULL,NULL) AS n FROM t1 ORDER BY id'];
        yield 'correlated comparison' => ['SELECT id,(SELECT t1.a,NULL FROM DUAL WHERE 0) <=> (t1.a,NULL) AS n FROM t1 ORDER BY id'];
        foreach (['1', '1.0', '1e0', "'1'"] as $left) {
            foreach (['1', '1.0', '1e0', "'1'"] as $right) {
                yield 'conversion ' . $left . ' to ' . $right => ['SELECT id,(SELECT ' . $left . ',NULL FROM DUAL WHERE 0) <=> (' . $right . ',NULL) AS same FROM t2 ORDER BY id'];
            }
        }
        yield 'outer column starts null' => ['SELECT id,(SELECT t1.a,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n FROM t1 WHERE id>=3 ORDER BY id'];
        yield 'outer expression starts null' => ['SELECT id,(SELECT t1.a+1,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n FROM t1 WHERE id>=3 ORDER BY id'];
        yield 'outer alias starts null' => ['SELECT a+1 AS v,(SELECT v,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n FROM t1 WHERE id>=3 ORDER BY id'];
        yield 'null constant warnings enabled' => ["SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO'; SELECT id,(SELECT 1/0,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n FROM t2 ORDER BY id"];
        yield 'third constant column' => ['SELECT id,(SELECT NULL,NULL,1 FROM DUAL WHERE 0) <=> (NULL,NULL,1) AS n FROM t2 ORDER BY id'];
        yield 'converted leading number' => ['SELECT id,(SELECT 1,1 FROM DUAL WHERE 0) <=> (1.0,1) AS n FROM t2 ORDER BY id'];
        yield 'converted leading string' => ["SELECT id,(SELECT '1',1 FROM DUAL WHERE 0) <=> (1,1) AS n FROM t2 ORDER BY id"];
        yield 'second string column' => ["SELECT id,(SELECT NULL,'1' FROM DUAL WHERE 0) <=> (NULL,'1') AS n FROM t2 ORDER BY id"];
        yield 'second null constant warnings enabled' => ["SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO'; SELECT id,(SELECT NULL,1/0 FROM DUAL WHERE 0) <=> (NULL,NULL) AS n FROM t2 ORDER BY id"];
        yield 'second conversion warnings' => ["SELECT id,(SELECT NULL,CAST('x' AS SIGNED) FROM DUAL WHERE 0) <=> (NULL,0) AS n FROM t2 ORDER BY id"];
        yield 'second assignment' => ['SET @v=0; SELECT id,(SELECT NULL,@v:=@v+1 FROM DUAL WHERE 0) <=> (NULL,1) AS n,@v FROM t2 ORDER BY id'];
    }

    #[DataProvider('providerStatements')]
    public function testRepeatedEmptyProjectionMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
