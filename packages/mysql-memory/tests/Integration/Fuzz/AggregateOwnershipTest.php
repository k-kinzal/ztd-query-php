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
final class AggregateOwnershipTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'group concatenation' => ['SELECT (SELECT GROUP_CONCAT(t1.a ORDER BY t1.a)) FROM t1'];
        yield 'grouped scalar concatenation' => ['SELECT (SELECT (GROUP_CONCAT(b ORDER BY id)) FROM t1)'];
        yield 'scalar concatenation wrapper' => ["SELECT (SELECT CONCAT(GROUP_CONCAT(b ORDER BY id),'') FROM t1),(SELECT IFNULL(GROUP_CONCAT(b ORDER BY id),'') FROM t1)"];
        yield 'scalar string extrema' => ['SELECT (SELECT MIN(b) FROM t1),(SELECT MAX(b) FROM t1)'];
        yield 'wide utf8mb4 concatenation functions' => ["SET NAMES utf8mb4; SET group_concat_max_len=4096; SELECT GROUP_CONCAT(a ORDER BY id),CONCAT(GROUP_CONCAT(a ORDER BY id),''),IFNULL(GROUP_CONCAT(a ORDER BY id),''),HEX(GROUP_CONCAT(a ORDER BY id)) FROM t1"];
        yield 'wide latin1 concatenation functions' => ["SET NAMES latin1; SET group_concat_max_len=4096; SELECT GROUP_CONCAT(a ORDER BY id),CONCAT(GROUP_CONCAT(a ORDER BY id),''),IFNULL(GROUP_CONCAT(a ORDER BY id),''),HEX(GROUP_CONCAT(a ORDER BY id)) FROM t1"];
        yield 'unconverted concatenation functions' => ["SET NAMES utf8mb4; SET character_set_results=NULL; SELECT GROUP_CONCAT(a ORDER BY id),CONCAT(GROUP_CONCAT(a ORDER BY id),''),IFNULL(GROUP_CONCAT(a ORDER BY id),''),HEX(GROUP_CONCAT(a ORDER BY id)) FROM t1"];
        yield 'constant stays local' => ['SELECT (SELECT SUM(1)) FROM t1'];
        yield 'star stays local' => ['SELECT (SELECT COUNT(*)) FROM t1'];
        yield 'mixed column levels' => ['SELECT (SELECT SUM(t1.a+t2.a) FROM t2) FROM t1'];
        yield 'two enclosing levels' => ['SELECT (SELECT (SELECT SUM(t1.a)) FROM t2 LIMIT 1) FROM t1'];
        yield 'nearest referenced level' => ['SELECT (SELECT (SELECT SUM(t1.a+t2.a)) FROM t2) FROM t1'];
        yield 'outer empty input' => ['SELECT (SELECT SUM(a)) FROM t1 WHERE 0'];
        yield 'having reads an outer result' => ['SELECT a AS x FROM t1 HAVING (SELECT SUM(x)) > 0'];
        yield 'latin1 concatenation' => ['SET NAMES latin1; SELECT (SELECT GROUP_CONCAT(t1.a ORDER BY t1.a)) FROM t1'];
        yield 'utf8mb4 concatenation' => ['SET NAMES utf8mb4; SELECT (SELECT GROUP_CONCAT(t1.a ORDER BY t1.a)) FROM t1'];
        yield 'short latin1 concatenation' => ['SET NAMES latin1; SET group_concat_max_len=100; SELECT (SELECT GROUP_CONCAT(t1.a ORDER BY t1.a)) FROM t1'];
        yield 'short utf8mb4 concatenation' => ['SET NAMES utf8mb4; SET group_concat_max_len=100; SELECT (SELECT GROUP_CONCAT(t1.a ORDER BY t1.a)) FROM t1'];
        if (!str_starts_with((string) getenv('MYSQL_VERSION'), '5.6.')) {
            yield 'JSON array' => ['SELECT (SELECT JSON_ARRAYAGG(t1.a)) FROM t1'];
            yield 'JSON object' => ['SELECT (SELECT JSON_OBJECTAGG(t1.id,t1.a)) FROM t1'];
        }
        yield 'outer count creates one group' => ['SELECT (SELECT COUNT(t1.a)) FROM t1'];
        yield 'outer sum leaves inner cardinality unchanged' => ['SELECT id,(SELECT SUM(t1.a) FROM t2) FROM t1 GROUP BY id ORDER BY id'];
        yield 'outer sum with limited inner rows' => ['SELECT (SELECT SUM(t1.a) FROM t2 LIMIT 1) FROM t1'];
        yield 'empty inner predicate preserves outer grouping' => ['SELECT (SELECT MAX(t1.a) WHERE 0) FROM t1'];
        yield 'explicit outer groups' => ['SELECT id,(SELECT SUM(a)) FROM t1 GROUP BY id ORDER BY id'];
        yield 'outer WHERE cannot own the aggregate' => ['SELECT id FROM t1 WHERE a = (SELECT MAX(t1.a) FROM t2) ORDER BY id'];
    }

    #[DataProvider('providerStatements')]
    public function testTheResolvedOwnerDeterminesTheAggregatedRows(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
