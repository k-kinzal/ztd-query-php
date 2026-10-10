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
final class HavingAggregatesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'outer SUM in HAVING' => ['SELECT COUNT(*) FROM t1 HAVING (SELECT SUM(t1.a)) > 0'];
        yield 'plain outer input is hidden' => ['SELECT COUNT(*) FROM t1 HAVING (SELECT t1.a) > 0'];
        yield 'result alias stays row dependent' => ['SELECT a AS x FROM t1 HAVING (SELECT SUM(x)) > 0'];
        yield 'unaliased input owns SUM' => ['SELECT a FROM t1 HAVING (SELECT SUM(a)) > 0'];
        yield 'local aggregate input' => ['SELECT COUNT(*) FROM t1 HAVING (SELECT MAX(a) FROM t2) > 0'];
        yield 'reduced subquery argument' => ['SELECT COUNT(*) FROM t1 HAVING (SELECT SUM((SELECT t1.a))) > 0'];
        yield 'table subquery argument' => ['SELECT COUNT(*) FROM t1 HAVING (SELECT SUM((SELECT t1.a FROM t2 LIMIT 1))) > 0'];
        yield 'limited aggregate query' => ['SELECT COUNT(*) FROM t1 HAVING (SELECT SUM(t1.a) FROM t2 LIMIT 1) > 0'];
        yield 'outer group concatenation' => ['SELECT COUNT(*) FROM t1 HAVING (SELECT GROUP_CONCAT(t1.a)) IS NOT NULL'];
        if (!str_starts_with((string) getenv('MYSQL_VERSION'), '5.6.')) {
            yield 'outer JSON array' => ['SELECT COUNT(*) FROM t1 HAVING (SELECT JSON_ARRAYAGG(t1.a)) IS NOT NULL'];
        }
        yield 'aggregate inside inner WHERE' => ['SELECT COUNT(*) FROM t1 HAVING (SELECT 1 WHERE SUM(t1.a)>0)'];
        yield 'ordinary inner WHERE' => ['SELECT COUNT(*) FROM t1 HAVING (SELECT 1 WHERE t1.a>0)'];
        yield 'aggregate and ordinary input' => ['SELECT COUNT(*) FROM t1 HAVING (SELECT SUM(t1.a)+t1.a)>0'];
        yield 'ordinary outer HAVING' => ['SELECT COUNT(*) FROM t1 HAVING (SELECT SUM(t1.a))>0 AND t1.a>0'];
        yield 'HAVING creates one group' => ['SELECT 1 FROM t1 HAVING (SELECT SUM(t1.a))>0'];
        yield 'later ordinary query is hidden' => ['SELECT 1 FROM t1 HAVING (SELECT SUM(t1.a))>0 AND (SELECT t1.a)>0'];
        yield 'explicit outer groups' => ['SELECT id FROM t1 GROUP BY id HAVING (SELECT SUM(t1.a)) > 0 ORDER BY id'];
        yield 'ordinary input after GROUP BY' => ['SELECT id FROM t1 GROUP BY id HAVING (SELECT t1.a) > 0 ORDER BY id'];
        yield 'undetermined grouped input' => ['SELECT a FROM t1 GROUP BY a HAVING (SELECT id)>0 ORDER BY a'];
        yield 'grouping mode disabled' => ["SET sql_mode=''; SELECT COUNT(*) FROM t1 HAVING (SELECT t1.a)>0"];
        yield 'materialized derived input diagnostic' => ['SELECT COUNT(*) FROM (SELECT a FROM t1 LIMIT 4) AS x HAVING (SELECT x.a)>0'];
        if (!str_starts_with((string) getenv('MYSQL_VERSION'), '5.6.')) {
            yield 'derived merge disabled' => ["SET optimizer_switch='derived_merge=off'; SELECT COUNT(*) FROM (SELECT a FROM t1) AS x HAVING (SELECT x.a)>0"];
        }
        yield 'derived input diagnostic' => ['SELECT COUNT(*) FROM (SELECT a FROM t1) AS x HAVING (SELECT x.a)>0'];
        if (!str_starts_with((string) getenv('MYSQL_VERSION'), '5.')) {
            yield 'common table diagnostic' => ['WITH x AS (SELECT a FROM t1) SELECT COUNT(*) FROM x HAVING (SELECT x.a)>0'];
            yield 'qualified table beside common table' => ['WITH t1 AS (SELECT a FROM t2) SELECT COUNT(*) FROM fz.t1 HAVING (SELECT t1.a)>0'];
        }
        yield 'aggregate owned through select list' => ['SELECT (SELECT SUM(a)) FROM t1 HAVING (SELECT t1.a)>0'];
    }

    #[DataProvider('providerStatements')]
    public function testArgumentsResolveInputsWhileOrdinaryExpressionsSeeResults(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
