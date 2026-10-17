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
final class ViewStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'writes and filter' => ['CREATE TABLE t(a INT,b INT,c INT DEFAULT 9); INSERT INTO t VALUES(1,1,1),(-1,3,3); CREATE VIEW v AS SELECT a AS x,b FROM t q WHERE q.a>0; INSERT INTO v VALUES(8,9); UPDATE v SET x=x*100 WHERE b<3; DELETE FROM v WHERE x=8; SELECT * FROM t ORDER BY a'];
        yield 'computed reads' => ['CREATE TABLE t(a INT,b INT); INSERT INTO t VALUES(1,2),(3,4); CREATE VIEW v AS SELECT a AS x,b+1 AS y FROM t; UPDATE v SET x=y WHERE y>3; SELECT * FROM t ORDER BY a'];
        yield 'duplicate update' => ['CREATE TABLE t(a INT PRIMARY KEY,b INT); CREATE VIEW v AS SELECT a AS x,b AS y FROM t; INSERT INTO v VALUES(1,2); INSERT INTO v VALUES(1,3) ON DUPLICATE KEY UPDATE y=y+1; SELECT * FROM t'];
        yield 'set and query inserts' => ['CREATE TABLE t(a INT,b INT); CREATE VIEW v AS SELECT a AS x,b AS y FROM t; INSERT INTO v SET x=1,y=2; INSERT INTO v(x,y) SELECT 3,4; SELECT * FROM t ORDER BY a'];
        yield 'inserted row alias' => ['CREATE TABLE t(a INT PRIMARY KEY,b INT); CREATE VIEW v AS SELECT a AS x,b AS y FROM t; INSERT INTO v VALUES(1,2); INSERT INTO v VALUES(1,3) AS r ON DUPLICATE KEY UPDATE y=r.y; SELECT * FROM t'];
        yield 'explicit inserted columns' => ['CREATE TABLE t(a INT PRIMARY KEY,b INT); CREATE VIEW v AS SELECT a AS x,b AS y FROM t; INSERT INTO v VALUES(1,2); INSERT INTO v VALUES(1,3) AS r(c,d) ON DUPLICATE KEY UPDATE y=r.d; SELECT * FROM t'];
        yield 'catalog replacement' => ['CREATE TABLE t(a INT,b INT); CREATE VIEW v AS SELECT * FROM t; DROP TABLE t; CREATE TABLE t(b INT,a INT); INSERT INTO t VALUES(5,6); SELECT * FROM v'];
        yield 'catalog replacement write' => ['CREATE TABLE t(a INT,b INT); CREATE VIEW v AS SELECT * FROM t; DROP TABLE t; CREATE TABLE t(b INT,a INT); INSERT INTO t VALUES(5,6); UPDATE v SET a=10 WHERE b=5; SELECT * FROM v'];
        yield 'inserted and default columns' => ['CREATE TABLE t(a INT PRIMARY KEY,b INT DEFAULT 7); CREATE VIEW v AS SELECT a AS x,b AS y FROM t; INSERT INTO v VALUES(1,2); INSERT INTO v VALUES(1,3) ON DUPLICATE KEY UPDATE y=VALUES(y); UPDATE v SET y=DEFAULT(y); SELECT * FROM t'];
        yield 'creation lexical mode' => ["CREATE TABLE t(a VARCHAR(10)); SET sql_mode='PIPES_AS_CONCAT'; CREATE VIEW v AS SELECT a||'x' AS s FROM t; SET sql_mode=''; DROP TABLE t; CREATE TABLE t(a VARCHAR(10)); INSERT INTO t VALUES('a'); SELECT * FROM v"];
        yield 'explicit view columns' => ['CREATE TABLE t(a INT,b INT); CREATE VIEW v(x,y) AS SELECT a,b FROM t; SHOW CREATE VIEW v'];
        yield 'alter view' => ['CREATE TABLE t(a INT,b INT); INSERT INTO t VALUES(1,2); CREATE VIEW v AS SELECT a FROM t; ALTER VIEW v(x) AS SELECT b FROM t; SELECT * FROM v; SHOW CREATE VIEW v'];
        yield 'scoped column name' => ['CREATE TABLE t(a INT,b INT); CREATE TABLE u(x INT); INSERT INTO t VALUES(1,2); INSERT INTO u VALUES(8); CREATE VIEW v AS SELECT a AS x,b AS y FROM t; UPDATE v SET y=(SELECT MAX(x) FROM u); SELECT * FROM t'];
        yield 'correlated reference' => ['CREATE TABLE t(a INT,b INT); CREATE TABLE u(z INT); INSERT INTO t VALUES(1,2); INSERT INTO u VALUES(8); CREATE VIEW v AS SELECT a AS x,b AS y FROM t; UPDATE v SET y=(SELECT MAX(z)+v.x FROM u); SELECT * FROM t'];
        yield 'delete limit' => ['CREATE TABLE t(a INT PRIMARY KEY,b INT); INSERT INTO t VALUES(1,0),(2,1),(3,1); CREATE VIEW v AS SELECT a AS x,b FROM t WHERE b=1; DELETE FROM v ORDER BY x LIMIT 1; SELECT * FROM t ORDER BY a'];
    }

    #[DataProvider('providerStatements')]
    public function testStatementsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
