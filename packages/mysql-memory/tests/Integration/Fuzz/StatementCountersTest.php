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
        yield 'savepoints and characteristics' => ['SET TRANSACTION ISOLATION LEVEL READ COMMITTED; BEGIN; SAVEPOINT s; ROLLBACK TO s; RELEASE SAVEPOINT s; COMMIT'];
        yield 'XA completion' => ["XA START 'counter_commit'; XA END 'counter_commit'; XA PREPARE 'counter_commit'; XA COMMIT 'counter_commit'; XA START 'counter_rollback'; XA END 'counter_rollback'; XA PREPARE 'counter_rollback'; XA ROLLBACK 'counter_rollback'; XA RECOVER"];
        yield 'table locks' => ['LOCK TABLES t1 READ; UNLOCK TABLES'];
        yield 'handler cursors' => ['HANDLER t1 OPEN; HANDLER t1 READ FIRST; HANDLER t1 READ `PRIMARY` FIRST; HANDLER t1 READ `PRIMARY`=(1); HANDLER t1 CLOSE'];
        yield 'schema inspections' => ['SHOW TABLES; SHOW OPEN TABLES; SHOW COLUMNS FROM t1; SHOW KEYS FROM t1; DESCRIBE t1; SHOW CREATE TABLE t1'];
        yield 'character catalogs' => ['SHOW CHARACTER SET; SHOW COLLATION'];
        yield 'engine and privilege catalogs' => ['SHOW ENGINES; SHOW PRIVILEGES'];
        yield 'program alterations and removals' => ["CREATE PROCEDURE p() DO 1; ALTER PROCEDURE p COMMENT 'counter'; DROP PROCEDURE p; CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 1; ALTER FUNCTION f COMMENT 'counter'; DROP FUNCTION f; CREATE TRIGGER tr BEFORE INSERT ON t1 FOR EACH ROW SET @a=1; DROP TRIGGER tr"];
        yield 'diagnostic statements' => ["SIGNAL SQLSTATE '01000'; GET DIAGNOSTICS @a=NUMBER; CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN END; RESIGNAL; END; CALL p()"];
        yield 'account privileges' => ["CREATE USER 'counter_test'@'counter.invalid'; GRANT SELECT ON t1 TO 'counter_test'@'counter.invalid'; REVOKE SELECT ON t1 FROM 'counter_test'@'counter.invalid'; REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'counter_test'@'counter.invalid'; RENAME USER 'counter_test'@'counter.invalid' TO 'counter_renamed'@'counter.invalid'; DROP USER 'counter_renamed'@'counter.invalid'"];
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
        if (!str_starts_with((string) getenv('MYSQL_VERSION'), '5.')) {
            yield 'roles and defaults' => ["CREATE USER 'counter_test'@'counter.invalid'; CREATE ROLE 'counter_role'@'counter.invalid'; GRANT 'counter_role'@'counter.invalid' TO 'counter_test'@'counter.invalid'; SET DEFAULT ROLE ALL TO 'counter_test'@'counter.invalid'; ALTER USER 'counter_test'@'counter.invalid' DEFAULT ROLE NONE; SET ROLE NONE; REVOKE 'counter_role'@'counter.invalid' FROM 'counter_test'@'counter.invalid'; DROP ROLE 'counter_role'@'counter.invalid'; DROP USER 'counter_test'@'counter.invalid'"];
        }
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

    /**
     * @return iterable<string, array{bool}>
     */
    public static function providerServers(): iterable
    {
        yield 'MySQL' => [false];
        yield 'memory' => [true];
    }

    #[DataProvider('providerServers')]
    public function testFlushStatusResetsTheReleaseScopeAcrossConnections(bool $memory): void
    {
        [$target] = Servers::shared();
        $target->repair($target->guard());
        $target->repair($target->memoryGuard());
        $dsn = $memory ? $target->memory : $target->native;
        $user = $memory ? 'root' : $target->nativeUser;
        $password = $memory ? '' : $target->nativePassword;
        $first = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $second = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $first->exec('FLUSH STATUS');
        $second->exec('FLUSH STATUS');
        $before = $first->query("SHOW GLOBAL STATUS LIKE 'Com_do'");
        self::assertNotFalse($before);
        $total = (int) $before->fetchColumn(1);
        $first->exec('DO 1');
        $second->exec('DO 2');
        $first->exec('FLUSH STATUS');
        $firstCounts = $first->query("SHOW SESSION STATUS WHERE Variable_name IN ('Com_do','Com_flush','Questions')");
        $secondCounts = $second->query("SHOW SESSION STATUS WHERE Variable_name IN ('Com_do','Com_flush','Questions')");
        $global = $first->query("SHOW GLOBAL STATUS LIKE 'Com_do'");
        self::assertNotFalse($firstCounts);
        self::assertNotFalse($secondCounts);
        self::assertNotFalse($global);
        self::assertSame(['Com_do' => '0', 'Com_flush' => '0', 'Questions' => '1'], $firstCounts->fetchAll(PDO::FETCH_KEY_PAIR));
        $legacy = str_starts_with($target->version, '5.6.');
        self::assertSame(['Com_do' => $legacy ? '1' : '0', 'Com_flush' => '0', 'Questions' => $legacy ? '2' : '1'], $secondCounts->fetchAll(PDO::FETCH_KEY_PAIR));
        self::assertSame($total + 2, (int) $global->fetchColumn(1));
    }

    #[DataProvider('providerServers')]
    public function testUptimeIncludesWaitsAndFlushDoesNotResetServerAge(bool $memory): void
    {
        [$target] = Servers::shared();
        $target->repair($target->guard());
        $target->repair($target->memoryGuard());
        $pdo = new PDO($memory ? $target->memory : $target->native, $memory ? 'root' : $target->nativeUser, $memory ? '' : $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $before = $pdo->query("SHOW GLOBAL STATUS LIKE 'Uptime'");
        self::assertNotFalse($before);
        $uptime = (int) $before->fetchColumn(1);
        $pdo->query('SELECT SLEEP(2)');
        $pdo->exec('FLUSH STATUS');
        $pdo->query('SELECT SLEEP(1)');
        $after = $pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Uptime_since_flush_status')");
        self::assertNotFalse($after);
        $values = $after->fetchAll(PDO::FETCH_KEY_PAIR);
        self::assertIsNumeric($values['Uptime']);
        self::assertIsNumeric($values['Uptime_since_flush_status']);

        self::assertGreaterThanOrEqual($uptime + 3, (int) $values['Uptime']);
        self::assertLessThanOrEqual($uptime + 5, (int) $values['Uptime']);
        self::assertGreaterThanOrEqual(1, (int) $values['Uptime_since_flush_status']);
        self::assertLessThanOrEqual(3, (int) $values['Uptime_since_flush_status']);
    }

    #[DataProvider('providerServers')]
    public function testPinnedTimestampControlsBothStatusClocksWithUnsignedWraparound(bool $memory): void
    {
        [$target] = Servers::shared();
        $target->repair($target->guard());
        $target->repair($target->memoryGuard());
        $pdo = new PDO($memory ? $target->memory : $target->native, $memory ? 'root' : $target->nativeUser, $memory ? '' : $target->nativePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('FLUSH STATUS');
        $pdo->exec('SET timestamp=1000.9');
        $first = $pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Uptime_since_flush_status')");
        self::assertNotFalse($first);
        $before = $first->fetchAll(PDO::FETCH_KEY_PAIR);
        $pdo->exec('SET timestamp=1003.1');
        $second = $pdo->query("SHOW SESSION STATUS WHERE Variable_name IN ('Uptime','Uptime_since_flush_status')");
        self::assertNotFalse($second);
        $after = $second->fetchAll(PDO::FETCH_KEY_PAIR);
        $schema = str_starts_with($target->version, '5.6.') ? 'information_schema' : 'performance_schema';
        $global = $pdo->query("SELECT VARIABLE_NAME, VARIABLE_VALUE FROM {$schema}.global_status WHERE VARIABLE_NAME IN ('Uptime','Uptime_since_flush_status') ORDER BY VARIABLE_NAME");
        $session = $pdo->query("SELECT VARIABLE_NAME, VARIABLE_VALUE FROM {$schema}.session_status WHERE VARIABLE_NAME IN ('Uptime','Uptime_since_flush_status') ORDER BY VARIABLE_NAME");
        self::assertNotFalse($global);
        self::assertNotFalse($session);
        self::assertIsString($before['Uptime']);
        self::assertIsString($before['Uptime_since_flush_status']);
        self::assertIsString($after['Uptime']);
        self::assertIsString($after['Uptime_since_flush_status']);
        self::assertIsNumeric($before['Uptime']);
        self::assertIsNumeric($before['Uptime_since_flush_status']);
        self::assertIsNumeric($after['Uptime']);
        self::assertIsNumeric($after['Uptime_since_flush_status']);
        self::assertSame(1, bccomp($before['Uptime'], '9223372036854775807', 0));
        self::assertSame('3', bcsub($after['Uptime'], $before['Uptime'], 0));
        self::assertSame('3', bcsub($after['Uptime_since_flush_status'], $before['Uptime_since_flush_status'], 0));
        self::assertSame(array_values($after), array_column($global->fetchAll(PDO::FETCH_NUM), 1));
        self::assertSame(array_values($after), array_column($session->fetchAll(PDO::FETCH_NUM), 1));
    }

    public function testReplicationInspectionCountersKeepReleaseAliases(): void
    {
        [$target] = Servers::shared();
        $legacy = str_starts_with($target->version, '5.');
        $binary = version_compare($target->version, '8.4.0', '<') ? 'MASTER' : 'BINARY LOG';
        $replicas = $legacy ? 'SLAVE HOSTS' : 'REPLICAS';
        $status = $legacy ? 'SLAVE STATUS' : 'REPLICA STATUS';
        [$isolated, , $server] = (new Servers())->start(true, true);
        $comparison = $isolated->compare("FLUSH LOCAL STATUS; SHOW {$binary} STATUS; SHOW {$replicas}; SHOW {$status}; SHOW BINARY LOGS; SHOW BINLOG EVENTS; SHOW RELAYLOG EVENTS; SHOW SESSION STATUS WHERE Variable_name LIKE 'Com_show_%'");
        $server->stop();

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
