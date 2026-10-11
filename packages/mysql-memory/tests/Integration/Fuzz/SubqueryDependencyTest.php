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
final class SubqueryDependencyTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'scalar random' => ['SELECT id, (SELECT RAND(17) FROM t2 WHERE id=1) FROM t1 ORDER BY id'];
        yield 'substituted random' => ['SELECT id, (SELECT RAND(17)) FROM t1 ORDER BY id'];
        yield 'tableless row random' => ['SELECT id, (0.5, 1) < (SELECT RAND(17), 1) FROM t1 ORDER BY id'];
        yield 'table row random' => ['SELECT id, (0.5, 1) < (SELECT RAND(17), 1 FROM t2 WHERE id=1) FROM t1 ORDER BY id'];
        yield 'limited random' => ['SELECT id, (SELECT RAND(17) FROM t2 WHERE id=1 LIMIT 1) FROM t1 ORDER BY id'];
        yield 'union random' => ['SELECT id, (SELECT RAND(17) FROM t2 WHERE id=1 UNION ALL SELECT RAND(17) WHERE 0) FROM t1 ORDER BY id'];
        yield 'session mutation' => ['SELECT id, (SELECT LAST_INSERT_ID(id) FROM t2 WHERE id=1) FROM t1 ORDER BY id'];
        yield 'variable assignment' => ['SELECT id, (SELECT @v := COALESCE(@v,0)+1 FROM t2 WHERE id=1) FROM t1 ORDER BY id'];
        yield 'assigned variable scalar read' => ['SELECT id, (@v:=CAST(id AS SIGNED)), (SELECT @v FROM t2 WHERE id=1) FROM t1 ORDER BY id'];
        yield 'assigned variable row read' => ['SELECT id, (@v:=CAST(id AS SIGNED)), (1,1) = (SELECT @v,1 FROM t2 WHERE id=1) FROM t1 ORDER BY id'];
        yield 'assigned variable membership' => ['SELECT id, (@v:=CAST(id AS SIGNED)), (1,1) IN (SELECT @v,id FROM t2) FROM t1 ORDER BY id'];
        yield 'random aggregate' => ['SELECT id, (SELECT SUM(RAND(17)) FROM t2) FROM t1 ORDER BY id'];
        yield 'random row aggregate' => ['SELECT id, (0.5,1) < (SELECT MIN(RAND(17)),1 FROM t2) FROM t1 ORDER BY id'];
        yield 'nested random' => ['SELECT id, (SELECT (SELECT RAND(17) FROM t2 WHERE id=1) FROM t2 WHERE id=2) FROM t1 ORDER BY id'];
        yield 'random filter' => ['SELECT id, (SELECT 1 FROM t2 WHERE id=1 AND RAND(17)>0.5) FROM t1 ORDER BY id'];
        yield 'materialized random' => ['SELECT id, (SELECT v FROM (SELECT RAND(17) AS v) AS d) FROM t1 ORDER BY id'];
        yield 'random comparison' => ['SELECT id, (SELECT RAND(17) FROM t2 WHERE id=1) > 0.5 FROM t1 ORDER BY id'];
        yield 'random string' => ['SELECT id, CONCAT((SELECT RAND(17) FROM t2 WHERE id=1)) FROM t1 ORDER BY id'];
        yield 'merged derived random' => ['SELECT id,(SELECT v FROM (SELECT RAND(17) v FROM t2 WHERE id=1) d) FROM t1 ORDER BY id'];
        yield 'limited derived random' => ['SELECT id,(SELECT v FROM (SELECT RAND(17) v FROM t2 WHERE id=1 LIMIT 1) d) FROM t1 ORDER BY id'];
        yield 'correlated materialized random' => ['SELECT id,(SELECT v FROM (SELECT RAND(17)+t1.id v LIMIT 1) d) FROM t1 ORDER BY id'];
        yield 'materialized random with changing filter' => ['SELECT id,(SELECT v FROM (SELECT RAND(17) v) d WHERE RAND(11)>0.5) FROM t1 ORDER BY id'];
        yield 'materialized common table random' => ['SELECT id,(WITH c AS (SELECT RAND(17) v) SELECT v FROM c) FROM t1 ORDER BY id'];
        yield 'merged common table random' => ['SELECT id,(WITH c AS (SELECT RAND(17) v FROM t2 WHERE id=1) SELECT v FROM c) FROM t1 ORDER BY id'];
    }

    #[DataProvider('providerStatements')]
    public function testDependenciesMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
