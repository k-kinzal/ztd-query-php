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
final class ScalarReductionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'literals and limits' => ['SELECT (SELECT 1), (SELECT NULL), (SELECT 2 LIMIT 0), (SELECT 3 LIMIT 1 OFFSET 1)'];
        yield 'dual and ordering' => ['SELECT (SELECT 1 FROM DUAL), (SELECT 1+2 FROM DUAL ORDER BY 1), (SELECT DISTINCT 1 FROM DUAL)'];
        yield 'grouping' => ['SELECT (SELECT 1 FROM DUAL GROUP BY 1)'];
        yield 'correlation' => ['SELECT (SELECT t1.id LIMIT 0), (SELECT t1.a) FROM t1'];
        yield 'nested correlation' => ['SELECT (SELECT (SELECT x.id LIMIT 0)) AS scalar_id FROM t1 AS x'];
        yield 'column display width' => ['CREATE TABLE widths(a INT(2) PRIMARY KEY); INSERT INTO widths VALUES(1); SELECT (SELECT a LIMIT 0) FROM widths'];
        yield 'nested expression' => ['SELECT (SELECT (SELECT 1 LIMIT 0))'];
        yield 'filters still run' => ['SELECT (SELECT 1 FROM DUAL WHERE FALSE), (SELECT 1 FROM DUAL HAVING FALSE)'];
        yield 'aggregate limit still runs' => ['SELECT (SELECT COUNT(*) LIMIT 0), (SELECT MAX(1))'];
        yield 'aggregate attribute' => ['SELECT (SELECT COUNT(*))'];
        yield 'correlated calculations' => ['SELECT (SELECT id+1 LIMIT 0), (SELECT ABS(id) LIMIT 0) FROM t1'];
        yield 'row count still checked' => ['SELECT (SELECT id FROM t1)'];
        yield 'row varying expression' => ['SET @value=0; SELECT (SELECT @value:=@value+1 LIMIT 0) FROM t1; SELECT @value'];
        if (!str_starts_with((string) getenv('MYSQL_VERSION'), '5.')) {
            yield 'SQL_NO_CACHE and FULL alias' => ['SELECT (SELECT SQL_NO_CACHE 1) AS full'];
            yield 'with clause' => ['SELECT (WITH c AS (SELECT 1) SELECT 1)'];
            yield 'rollup has multiple rows' => ['SELECT (SELECT 1 FROM DUAL GROUP BY 1 WITH ROLLUP)'];
            yield 'window attributes' => ['SELECT (SELECT ROW_NUMBER() OVER ()), (SELECT ROW_NUMBER() OVER () LIMIT 0)'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testScalarReductionMatchesTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
