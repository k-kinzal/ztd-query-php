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
final class LiteralKeyReadTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'present key' => ['SELECT * FROM t1 WHERE id=2'];
        yield 'missing key' => ['SELECT * FROM t1 WHERE id=99'];
        yield 'commuted equality' => ['SELECT * FROM t1 AS q WHERE 2=q.id'];
        yield 'null-safe equality' => ['SELECT * FROM t1 WHERE id<=>2'];
        yield 'rejected row' => ['SELECT * FROM t1 WHERE id=2 AND a=999'];
        yield 'contradictory key' => ['SELECT * FROM t1 WHERE id=1 AND id=2'];
        yield 'alternative key' => ['SELECT * FROM t1 WHERE id=1 OR id=2'];
        yield 'diagnostics on candidate only' => ['SELECT id FROM t1 WHERE b=0 AND id=2'];
        yield 'missing key projection' => ['SELECT 1/0 FROM t1 WHERE id=99'];
        yield 'assignment on candidate' => ['SET @v=0; SELECT id FROM t1 WHERE (@v:=@v+1)>0 AND id=2; SELECT @v'];
        yield 'rejected conversion candidate' => ['SELECT id FROM t1 WHERE b=1 AND id=2'];
        yield 'conversion after key' => ['SELECT id FROM t1 WHERE id=2 AND b=0'];
        yield 'assignment changes a passing condition' => ['SET @v=0; SELECT id FROM t1 WHERE (@v:=@v+1)=1 AND id=2; SELECT @v'];
        yield 'assignment changes a rejected condition' => ['SET @v=0; SELECT id FROM t1 WHERE (@v:=@v+1)=2 AND id=2; SELECT @v'];
        yield 'assignment on a missing candidate' => ['SET @v=0; SELECT id FROM t1 WHERE (@v:=@v+1)>0 AND id=99; SELECT @v'];
        yield 'assignment with zero limit' => ['SET @v=0; SELECT id FROM t1 WHERE (@v:=@v+1)>0 AND id=2 LIMIT 0; SELECT @v'];
        yield 'conversion with zero limit' => ['SELECT id FROM t1 WHERE b=0 AND id=2 LIMIT 0'];
        yield 'assignment before aggregation' => ['SET @v=0; SELECT SUM(a) FROM t1 WHERE (@v:=@v+1)>0 AND id=2; SELECT @v'];
        yield 'random predicate' => ['SELECT id,(SELECT 1 FROM t2 WHERE id=1 AND RAND(17)>0.5) FROM t1 ORDER BY id'];
        yield 'random before conversion' => ['SELECT id FROM t1 WHERE RAND(17)>2 AND b=0 AND id=2'];
        yield 'conversion before random' => ['SELECT id FROM t1 WHERE b=0 AND RAND(17)>2 AND id=2'];
        yield 'assignment before random' => ['SET @v=0; SELECT id FROM t1 WHERE (@v:=@v+1)>0 AND RAND(17)>2 AND id=2; SELECT @v'];
        yield 'random before assignment' => ['SET @v=0; SELECT id FROM t1 WHERE RAND(17)>2 AND (@v:=@v+1)>0 AND id=2; SELECT @v'];
        yield 'passing random and assignment' => ['SET @v=0; SELECT id FROM t1 WHERE RAND(17)<2 AND (@v:=@v+1)>0 AND id=2; SELECT @v'];
        yield 'aggregate present' => ['SELECT COUNT(*),SUM(a) FROM t1 WHERE id=2'];
        yield 'aggregate missing' => ['SELECT COUNT(*),MAX(a) FROM t1 WHERE id=99'];
        yield 'locking read' => ['START TRANSACTION; SELECT * FROM t1 WHERE id=2 FOR UPDATE; ROLLBACK'];
        yield 'serializable read' => ['SET TRANSACTION ISOLATION LEVEL SERIALIZABLE; START TRANSACTION; SELECT * FROM t1 WHERE id=2; ROLLBACK'];
        yield 'integer precision' => ['CREATE TABLE key_read (id BIGINT UNSIGNED PRIMARY KEY); INSERT INTO key_read VALUES (18446744073709551614),(18446744073709551615); SELECT * FROM key_read WHERE id=18446744073709551615; DROP TABLE key_read'];
        yield 'string key coercion' => ["CREATE TABLE key_read (id VARCHAR(3) PRIMARY KEY); INSERT INTO key_read VALUES ('1'),('01'); SELECT * FROM key_read WHERE id=1 ORDER BY id; DROP TABLE key_read"];
        yield 'composite key' => ['CREATE TABLE key_read (a INT,b INT,c INT,PRIMARY KEY(a,b)); INSERT INTO key_read VALUES (1,1,10),(1,2,20),(2,1,30); SELECT * FROM key_read WHERE a=1 AND b=2; SELECT * FROM key_read WHERE a=1 ORDER BY b; DROP TABLE key_read'];
        yield 'remaining condition' => ['CREATE TABLE key_read (id INT PRIMARY KEY,v INT); INSERT INTO key_read VALUES (1,10),(2,20); SELECT * FROM key_read WHERE id=1 AND v=20; DROP TABLE key_read'];
    }

    #[DataProvider('providerStatements')]
    public function testKeyReadMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
