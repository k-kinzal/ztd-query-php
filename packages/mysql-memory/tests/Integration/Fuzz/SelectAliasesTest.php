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
final class SelectAliasesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'prior column' => ['SELECT a AS s, (SELECT s) FROM t1 ORDER BY id'];
        yield 'aggregate result' => ['SELECT SUM(a) AS s, (SELECT s) FROM t1'];
        yield 'aggregate inside nested aggregate' => ['SELECT SUM(a) AS s, (SELECT MAX(s)) FROM t1'];
        yield 'forward column' => ['SELECT (SELECT s), a AS s FROM t1'];
        yield 'same list hidden' => ['SELECT a AS s, s FROM t1'];
        yield 'forward constant' => ['SELECT (SELECT s), 1 AS s'];
        yield 'aggregate of prior column' => ['SELECT a AS s, (SELECT SUM(s)) FROM t1 ORDER BY id'];
        yield 'prior expression' => ['SELECT a+1 AS s, (SELECT s) FROM t1 ORDER BY id'];
        yield 'aggregate of prior expression' => ['SELECT a+1 AS s, (SELECT SUM(s)) FROM t1 ORDER BY id'];
        yield 'correlated lookup' => ['SELECT a AS s, (SELECT s FROM t2 WHERE t2.id=t1.id) FROM t1 ORDER BY id'];
        yield 'different prior columns' => ['SELECT a AS s, b AS s, (SELECT s) FROM t1'];
        yield 'identical prior columns' => ['SELECT a AS s, a AS s, (SELECT s) FROM t1 ORDER BY id'];
        yield 'assignment alias reevaluated' => ['SELECT (@v:=COALESCE(@v,0)+1) AS x, (SELECT x) FROM t1 ORDER BY id'];
        yield 'nested table lookup' => ['SELECT a AS s, (SELECT s FROM t2 LIMIT 1) FROM t1 ORDER BY id'];
        yield 'later duplicate column' => ['SELECT a AS x, (SELECT x), b AS x FROM t1'];
        yield 'later identical column' => ['SELECT a AS x, (SELECT x), a AS x FROM t1 ORDER BY id'];
        yield 'two forward columns' => ['SELECT (SELECT x), a AS x, a AS x FROM t1'];
        yield 'computed item precedence' => ['SELECT a+1 AS x, (SELECT SUM(x)), b AS x FROM t1'];
        yield 'inner result alias' => ['SELECT a AS x, (SELECT x AS x) FROM t1 ORDER BY id'];
        yield 'table with no matching name' => ['SELECT a AS x, (SELECT x FROM t2 LIMIT 1) FROM t1 ORDER BY id'];
        yield 'two query levels' => ['SELECT a AS x, (SELECT (SELECT x)) FROM t1 ORDER BY id'];
        yield 'input column precedence' => ['SELECT a AS b, (SELECT b) FROM t1 ORDER BY id'];
        yield 'aggregate alias rejected' => ['SELECT SUM(a) AS x, (SELECT x) FROM t1'];
        yield 'outer-owned aggregate alias rejected' => ['SELECT (SELECT SUM(t1.a)) AS x, (SELECT x) FROM t1'];
        yield 'independent aggregate alias' => ['SELECT (SELECT MAX(a) FROM t2) AS x, (SELECT x) FROM t1 ORDER BY id'];
        yield 'self reference' => ['SELECT (SELECT x) AS x FROM t1'];
        yield 'initialized assignment alias' => ['SET @v=0; SELECT (@v:=COALESCE(@v,0)+1) AS x, (SELECT x) FROM t1 ORDER BY id'];
        yield 'explicit null assignment alias' => ['SET @v=NULL; SELECT (@v:=COALESCE(@v,0)+1) AS x, (SELECT x) FROM t1 ORDER BY id'];
        yield 'independent string scalar' => ['SELECT (SELECT b FROM t1 LIMIT 1)'];
        yield 'string scalar with fallback' => ["SELECT COALESCE((SELECT b),'x') FROM t1 ORDER BY id"];
        yield 'inner input column precedence' => ['SELECT a AS x, (SELECT x FROM t2 WHERE id=t1.id) FROM t1 ORDER BY id'];

    }

    #[DataProvider('providerStatements')]
    public function testScalarQueriesResolveVisibleSelectItems(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
