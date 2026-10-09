<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Differential;
use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class DifferentialTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function providerWrites(): iterable
    {
        foreach ([
            'UPDATE t1 SET a = a',
            'UPDATE t1 SET a = 10 WHERE id <= 2',
            'UPDATE t1 SET a = a WHERE id < 0',
            'UPDATE t1 SET a = a ORDER BY id LIMIT 2',
            'UPDATE t1 JOIN t2 ON t1.id = t2.id SET t1.a = t1.a, t2.a = 10',
            'UPDATE t1 JOIN t2 ON t2.a > 0 SET t1.a = t1.a',
            'UPDATE IGNORE t2 SET name = \'x\'',
            'INSERT INTO t2 (id, name) VALUES (1, \'x\') ON DUPLICATE KEY UPDATE name = \'x\'',
            'INSERT INTO t2 (id, name) VALUES (1, \'x\') ON DUPLICATE KEY UPDATE name = \'changed\'',
            'INSERT INTO t2 (id, name) VALUES (1, \'x\') ON DUPLICATE KEY UPDATE name = \'X\'',
            'INSERT INTO t2 (name) VALUES (\'new\') ON DUPLICATE KEY UPDATE name = \'new\'',
            'INSERT INTO t2 (name) VALUES (\'new\'), (\'other\')',
            'INSERT INTO t2 (id, name) VALUES (7, \'new\'), (8, \'other\')',
            'INSERT INTO t2 (id, name) VALUES (7, \'new\'), (1, \'x\') ON DUPLICATE KEY UPDATE name = name',
            'INSERT INTO t2 (name) VALUES (\'x\') ON DUPLICATE KEY UPDATE name = name',
            'INSERT INTO t2 (name) VALUES (\'x\') ON DUPLICATE KEY UPDATE name = \'changed\'',
            'INSERT IGNORE INTO t2 (name) VALUES (\'x\')',
            'INSERT INTO t2 (id, name) VALUES (1, \'x\') ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)',
            'DELETE FROM t1 WHERE id < 3',
        ] as $sql) {
            foreach ([true, false] as $emulate) {
                foreach ([true, false] as $foundRows) {
                    yield $sql . ', emulate=' . (int) $emulate . ', found=' . (int) $foundRows => [$sql, $emulate, $foundRows];
                }
            }
        }
    }

    #[DataProvider('providerWrites')]
    public function testWriteCountsMatchTheServer(string $sql, bool $emulate, bool $foundRows): void
    {
        [$base] = Servers::shared();
        $target = new Differential($base->native, $base->nativeUser, $base->nativePassword, $base->memory, $emulate, $base->version, $base->guardUser, $foundRows);
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, $sql);
        self::assertNull($comparison->difference, $sql . "\n" . $comparison->difference);
    }

    public function testEveryResultOfAMultiStatementQueryMatchesTheServer(): void
    {
        $sql = 'INSERT INTO t2 (name) VALUES (\'new\'); SELECT LAST_INSERT_ID(), ROW_COUNT(); SELECT id FROM t1 ORDER BY id DESC LIMIT 2';
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerResolutionErrors(): iterable
    {
        yield 'IN operand before list' => ['SELECT missing IN ((1 = ALL (SELECT 1,2)), 3)'];
        yield 'single IN element' => ['SELECT missing NOT IN ((1 = ALL (SELECT 1,2))) FROM t1'];
        yield 'row with scalar quantified operator' => ['SELECT (missing,1) = ALL (SELECT 1,2,3)'];
        yield 'scalar quantified operator' => ['SELECT missing = ALL (SELECT 1,2)'];
        yield 'names in both quantified operands' => ['SELECT missing = ALL (SELECT missing2)'];
        yield 'names in both membership operands' => ['SELECT missing IN (SELECT missing2)'];
        yield 'separate predicates with equal widths' => ['SELECT 1 IN (SELECT 1,2), missing = ALL (SELECT 1,2)'];
        yield 'row membership width' => ['SELECT (1,2) IN (SELECT 1,2,3), missing'];
        yield 'window count before frame' => ['SELECT NTILE(missing) OVER (RANGE CURRENT ROW EXCLUDE CURRENT ROW)'];
        yield 'window name before null treatment' => ['SELECT NTH_VALUE(1,1) IGNORE NULLS OVER missing'];
        yield 'following field before null treatment' => ['SELECT NTH_VALUE(1,1) IGNORE NULLS OVER (), missing'];
        yield 'repeated window before null treatment' => ['SELECT NTH_VALUE(1,1) IGNORE NULLS OVER () WINDOW w AS (), w AS ()'];
        yield 'window ordering before null treatment' => ['SELECT NTH_VALUE(1,1) IGNORE NULLS OVER (ORDER BY 1)'];
        yield 'null treatment before counting edge' => ['SELECT NTH_VALUE(1,1) FROM LAST IGNORE NULLS OVER ()'];
        yield 'counting edge before row number' => ['SELECT NTH_VALUE(1,0) FROM LAST OVER ()'];
        yield 'primary key with earlier null' => ['CREATE TABLE x(a INT NULL KEY)'];
        yield 'primary key with null then not null' => ['CREATE TABLE x(a INT NULL NOT NULL PRIMARY KEY)'];
        yield 'routine parameter type warnings' => ['CREATE PROCEDURE p(a INT(11), b NATIONAL CHAR(3)) SELECT a'];
        yield 'routine return type warnings' => ['CREATE FUNCTION f() RETURNS CHAR(0) ASCII RETURN 1'];
        yield 'routine leading binary attribute' => ['CREATE FUNCTION f() RETURNS CHAR(0) BINARY ASCII RETURN 1'];
        yield 'routine trailing binary attribute' => ['CREATE FUNCTION f() RETURNS CHAR(0) ASCII BINARY RETURN 1'];
        yield 'routine implicit binary return collation' => ['CREATE FUNCTION f() RETURNS CHAR(0) BINARY RETURN 1'];
        yield 'routine national binary attribute' => ['CREATE FUNCTION f() RETURNS NCHAR(0) BINARY RETURN 1'];
        yield 'routine fractional return width' => ['CREATE FUNCTION f() RETURNS FLOAT(0,0) RETURN 1'];
        yield 'routine bit return width' => ['CREATE FUNCTION f() RETURNS BIT(0) RETURN 1'];
        yield 'routine bit return overflow' => ['CREATE FUNCTION f() RETURNS BIT(65) RETURN 1'];
        yield 'routine floating return precision' => ['CREATE FUNCTION f() RETURNS FLOAT(54) RETURN 1'];
        yield 'routine decimal return precision' => ['CREATE FUNCTION f() RETURNS DECIMAL(66,0) RETURN 1'];
        yield 'routine decimal scale order' => ['CREATE FUNCTION f() RETURNS DECIMAL(2,3) RETURN 1'];
        yield 'routine temporal return precision' => ['CREATE FUNCTION f() RETURNS TIME(7) RETURN 1'];
        yield 'routine parameter width' => ['CREATE PROCEDURE p(a INT(256)) SELECT a'];
        yield 'routine fractional parameter scale' => ['CREATE PROCEDURE p(a DOUBLE(50,31)) SELECT a'];
        yield 'column bit width' => ['CREATE TABLE x(a BIT(65))'];
        yield 'column temporal precision' => ['CREATE TABLE x(a DATETIME(7))'];
        yield 'kill null' => ['KILL QUERY NULL'];
        yield 'kill negative' => ['KILL QUERY -1'];
        yield 'kill user conversion' => ['KILL USER()'];
        yield 'kill constant subquery' => ['KILL (SELECT 0)'];
        yield 'kill table dependency' => ['KILL (SELECT 0 FROM missing)'];
        yield 'kill routine dependency' => ['KILL missing()'];
        yield 'kill unknown column' => ['KILL missing'];
        yield 'kill row operand' => ['KILL (1,2)'];
        yield 'kill aggregate' => ['KILL COUNT(*)'];
        yield 'kill window function' => ['KILL ROW_NUMBER() OVER ()'];
        yield 'kill runtime subquery error' => ['KILL (SELECT 1 UNION SELECT 2)'];
        yield 'clone local without plugin' => ["CLONE LOCAL DATA DIRECTORY 'text'"];
        yield 'json document type before cast warnings' => ["SELECT JSON_EXTRACT(CAST('bad' AS SIGNED), '$')"];
        yield 'member document type before cast warnings' => ["SELECT 1 MEMBER OF (CAST('bad' AS DATETIME))"];
        yield 'member null before document type' => ["SELECT NULL MEMBER OF (CAST('bad' AS SIGNED))"];
        yield 'table outside the current database' => ['USE information_schema; SELECT * FROM fz.t1 ORDER BY id'];
    }

    #[DataProvider('providerResolutionErrors')]
    public function testResolutionErrorsMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }

    public function testCompareDistinguishesAVolatileReferenceFromAMatch(): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare('SELECT UUID()');

        self::assertTrue($comparison->volatile);
        self::assertNull($comparison->difference);
    }

    public function testCompareReleasesTheBackupLockBeforeRepairingTheServer(): void
    {
        [$target] = Servers::shared();
        $target->guard()->exec('SET SESSION lock_wait_timeout = 2');
        $comparison = $target->compare('LOCK INSTANCE FOR BACKUP');
        $dropped = $target->guard()->exec('DROP DATABASE fz');

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
        self::assertNotFalse($dropped);
    }
}
