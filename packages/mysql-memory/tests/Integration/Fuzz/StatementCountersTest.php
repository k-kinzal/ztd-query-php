<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class StatementCountersTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        yield 'select and expressions' => ['SELECT 1; DO 2; SET @a=3'];
        yield 'maximum decimal metadata' => ['SELECT CAST(1 AS DECIMAL(65,30))+1, (CAST(1 AS DECIMAL(65,30))+1)+1, CAST(1 AS DECIMAL(65,0))-1, CAST(1 AS DECIMAL(65,30))*CAST(1 AS DECIMAL(65,30))'];
        yield 'cast string precision' => ["SELECT CAST(REPEAT('0',100) AS SIGNED), CAST(REPEAT('0',100) AS SIGNED)+1, CAST(REPEAT('0',100) AS UNSIGNED)+1, CAST(REPEAT('0',100) AS SIGNED)*1"];
        yield 'write kinds' => ['INSERT INTO t1(id) VALUES (6); INSERT INTO t1(id) SELECT 7; REPLACE INTO t1(id) VALUES (6); REPLACE INTO t1(id) SELECT 7; UPDATE t1 SET a=0 WHERE id=6; DELETE FROM t1 WHERE id=7'];
        yield 'joined writes' => ['UPDATE t1 JOIN t2 ON t1.id=t2.id SET t1.a=1; DELETE t1 FROM t1 JOIN t2 ON t1.id=t2.id'];
        yield 'create and alter' => ['CREATE TABLE t (a INT); ALTER TABLE t ADD b INT; CREATE INDEX k ON t(a); DROP INDEX k ON t; TRUNCATE TABLE t; DROP TABLE t'];
        yield 'transactions' => ['BEGIN; SELECT 1; COMMIT; START TRANSACTION; ROLLBACK'];
        yield 'SQL preparation' => ["PREPARE s FROM 'SELECT ?'; SET @a=1; EXECUTE s USING @a; DEALLOCATE PREPARE s"];
        yield 'initial string parameter' => ["PREPARE s FROM 'SELECT ?'; SET @a='abc'; EXECUTE s USING @a; DEALLOCATE PREPARE s"];
        yield 'initial NULL parameter' => ["PREPARE s FROM 'SELECT ?'; SET @a=NULL; EXECUTE s USING @a; DEALLOCATE PREPARE s"];
        yield 'parameter type transitions' => ["PREPARE s FROM 'SELECT ?'; SET @a=1; EXECUTE s USING @a; SET @a=2; EXECUTE s USING @a; SET @a='3'; EXECUTE s USING @a; SET @a=3.5; EXECUTE s USING @a; SET @a=NULL; EXECUTE s USING @a; DEALLOCATE PREPARE s"];
        yield 'arithmetic parameter transitions' => ["PREPARE s FROM 'SELECT ?+1'; SET @a=1; EXECUTE s USING @a; SET @a=3.5; EXECUTE s USING @a; DEALLOCATE PREPARE s"];
        yield 'cast parameter transitions' => ["PREPARE s FROM 'SELECT CAST(? AS SIGNED)'; SET @a=1; EXECUTE s USING @a; SET @a=3.5; EXECUTE s USING @a; DEALLOCATE PREPARE s"];
        yield 'warning counts' => ["SELECT 'x'+0; SHOW WARNINGS; SHOW COUNT(*) WARNINGS; SHOW ERRORS; SHOW COUNT(*) ERRORS"];
        yield 'explained kinds' => ['EXPLAIN SELECT * FROM t1; EXPLAIN UPDATE t1 SET a=1; EXPLAIN DELETE FROM t1'];
        yield 'flush again' => ['SELECT 1; FLUSH STATUS; SELECT 2'];
        yield 'procedure statements' => ['CREATE PROCEDURE p() BEGIN SET @a=1; DO 2; SELECT 3; END; CALL p()'];
        yield 'procedure locals' => ['CREATE PROCEDURE p() BEGIN DECLARE a INT DEFAULT 1; SET a=2; IF a=2 THEN SET a=3; END IF; END; CALL p()'];
        yield 'caught execution failure' => ['CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; SELECT * FROM missing; SELECT missing FROM t1; EXECUTE missing; DEALLOCATE PREPARE missing; END; CALL p()'];
    }

    #[DataProvider('providerStatements')]
    public function testCommandAndRequestCountsMatchInTheSession(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare("FLUSH STATUS; {$sql}; SHOW SESSION STATUS WHERE Variable_name LIKE 'Com_%' OR Variable_name='Questions'");

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerPrograms(): iterable
    {
        yield 'function return' => ['CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 1; SELECT f()'];
        yield 'local assignments and conditions' => ['CREATE PROCEDURE p() BEGIN DECLARE a INT DEFAULT 1; SET a=2; IF a=2 THEN SET a=3; END IF; END; CALL p()'];
        yield 'loop' => ['CREATE PROCEDURE p() BEGIN DECLARE a INT DEFAULT 0; WHILE a<3 DO SET a=a+1; END WHILE; END; CALL p()'];
        yield 'SQL execution' => ["PREPARE s FROM 'SELECT 1'; EXECUTE s; DEALLOCATE PREPARE s"];
    }

    #[DataProvider('providerPrograms')]
    public function testQueriesIncludesProgramWorkWhileQuestionsCountsClients(string $sql): void
    {
        [$target] = Servers::shared();
        $table = str_starts_with($target->version, '5.6.') ? 'information_schema.GLOBAL_STATUS' : 'performance_schema.global_status';
        $comparison = $target->compare("SELECT CAST(VARIABLE_VALUE AS UNSIGNED) INTO @before FROM {$table} WHERE VARIABLE_NAME='Queries'; {$sql}; SELECT CAST(VARIABLE_VALUE AS UNSIGNED)-@before FROM {$table} WHERE VARIABLE_NAME='Queries'");

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }

    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function providerConnections(): iterable
    {
        yield 'MySQL native preparation' => [false, false];
        yield 'memory native preparation' => [true, false];
        yield 'MySQL client preparation' => [false, true];
        yield 'memory client preparation' => [true, true];
    }

    #[DataProvider('providerConnections')]
    public function testPdoPreparationCountsProtocolRequests(bool $memory, bool $emulate): void
    {
        [$target] = Servers::shared();
        $target->repair($target->guard());
        $target->repair($target->memoryGuard());
        $pdo = new PDO($memory ? $target->memory : $target->native, $memory ? 'root' : $target->nativeUser, $memory ? '' : $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => $emulate]);
        $pdo->exec('FLUSH STATUS');
        $statement = $pdo->prepare('SELECT ?');
        self::assertNotFalse($statement);
        $statement->bindValue(1, 7, PDO::PARAM_INT);
        $statement->execute();
        $metadata = $statement->getColumnMeta(0);
        self::assertNotFalse($metadata);
        self::assertSame($emulate || version_compare($target->version, '8.0.22', '<') ? ['not_null'] : [], $metadata['flags']);
        self::assertSame([[7]], $statement->fetchAll(PDO::FETCH_NUM));
        unset($statement);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        $status = $pdo->query("SHOW SESSION STATUS WHERE Variable_name IN ('Questions','Com_select','Com_stmt_prepare','Com_stmt_execute','Com_stmt_close','Com_stmt_reprepare')");
        self::assertNotFalse($status);
        $reprepared = !$emulate && version_compare($target->version, '8.0.22', '>=');
        $counts = $status->fetchAll(PDO::FETCH_KEY_PAIR);
        ksort($counts);
        self::assertSame(['Com_select' => '1', 'Com_stmt_close' => $emulate ? '0' : '1', 'Com_stmt_execute' => $emulate ? '0' : '1', 'Com_stmt_prepare' => $emulate ? '0' : ($reprepared ? '2' : '1'), 'Com_stmt_reprepare' => $reprepared ? '1' : '0', 'Questions' => '2'], $counts);
    }
}
