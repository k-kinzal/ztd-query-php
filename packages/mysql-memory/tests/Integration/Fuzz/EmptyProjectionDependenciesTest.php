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
final class EmptyProjectionDependenciesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'routine parameter' => ['CREATE PROCEDURE ep(IN v INT) SELECT (SELECT v,NULL FROM DUAL WHERE 0) <=> (1,NULL) AS n; CALL ep(1); CALL ep(NULL)'];
        yield 'routine local' => ['CREATE PROCEDURE ep() BEGIN DECLARE v INT DEFAULT 1; SELECT (SELECT v,NULL FROM DUAL WHERE 0) <=> (1,NULL) AS n; SET v=NULL; SELECT (SELECT v,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n; END; CALL ep()'];
        yield 'nested scalar constant' => ['SELECT (SELECT (SELECT 1),NULL FROM DUAL WHERE 0) <=> (1,NULL) AS n'];
        yield 'nested scalar constant filtered' => ['SELECT (SELECT (SELECT 1 FROM DUAL WHERE 1),NULL FROM DUAL WHERE 0) <=> (1,NULL) AS n'];
        yield 'nested scalar empty' => ['SELECT (SELECT (SELECT 1 FROM DUAL WHERE 0),NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n'];
        yield 'nested scalar table' => ['SELECT (SELECT (SELECT a FROM t1 WHERE id=1),NULL FROM DUAL WHERE 0) <=> (10,NULL) AS n'];
        yield 'nested scalar many rows' => ['SELECT (SELECT (SELECT a FROM t1),NULL FROM DUAL WHERE 0) <=> (10,NULL) AS n'];
        yield 'nested scalar correlated' => ['SELECT id,(SELECT (SELECT t1.a FROM DUAL WHERE 1),NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n FROM t1 ORDER BY id'];
        yield 'nested scalar unused' => ['SELECT (SELECT NULL,(SELECT 1/0 FROM DUAL WHERE 1) FROM DUAL WHERE 0) <=> (1,NULL) AS n'];
        yield 'nested scalar warning' => ['SELECT (SELECT (SELECT 1/0 FROM DUAL WHERE 1),NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n'];
        yield 'nested scalar unused cardinality' => ['SELECT (SELECT NULL,(SELECT a FROM t1) FROM DUAL WHERE 0) <=> (1,NULL) AS n'];
        yield 'nested scalar assignment' => ['SET @v=0; SELECT id,(SELECT (SELECT @v:=@v+1 FROM DUAL WHERE 1),NULL FROM DUAL WHERE 0) <=> (1,NULL) AS n,@v FROM t2 ORDER BY id'];
        yield 'nested scalar buffered input' => ['SELECT (SELECT (SELECT t1.a FROM DUAL WHERE 1),NULL FROM t1 WHERE 0) <=> (NULL,NULL) AS n'];
        yield 'default value' => ['CREATE TABLE dp(a INT DEFAULT 7); SELECT (SELECT DEFAULT(a),NULL FROM dp WHERE 0) <=> (0,NULL) AS n'];
        yield 'default null' => ['CREATE TABLE dp(a INT DEFAULT NULL); SELECT (SELECT DEFAULT(a),NULL FROM dp WHERE 0) <=> (NULL,NULL) AS n'];
        yield 'default expression' => ['CREATE TABLE dp(a INT DEFAULT 7); SELECT (SELECT DEFAULT(a)+1,NULL FROM dp LIMIT 0) <=> (0,NULL) AS n'];
        yield 'default unused' => ['CREATE TABLE dp(a INT DEFAULT 7); SELECT (SELECT NULL,DEFAULT(a)/0 FROM dp WHERE 0) <=> (1,NULL) AS n'];
    }

    #[DataProvider('providerStatements')]
    public function testDependenciesMatchMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
