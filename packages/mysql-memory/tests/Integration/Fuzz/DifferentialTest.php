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
        yield 'cast floating precision before operand' => ['SELECT CAST(missing AS FLOAT(54))'];
        yield 'cast signed precision overflow' => ['SELECT CAST(1 AS FLOAT(2147483648))'];
        yield 'cast unsigned precision overflow' => ['SELECT CAST(1 AS FLOAT(4294967295))'];
        yield 'cast precision wraparound' => ['SELECT CAST(1 AS FLOAT(4294967296))'];
        yield 'cast maximum unsigned precision' => ['SELECT CAST(1 AS FLOAT(18446744073709551615))'];
        yield 'json object aggregate in kill' => ['KILL JSON_OBJECTAGG(1,1)'];
        yield 'json object aggregate in where' => ['SELECT 1 WHERE JSON_OBJECTAGG(1,1)'];
        yield 'alter force validation before missing table' => ['ALTER TABLE missing WITH VALIDATION, FORCE'];
        yield 'alter discard after missing table' => ['ALTER TABLE missing WITH VALIDATION, DISCARD TABLESPACE'];
        yield 'alter add with validation' => ['ALTER TABLE t1 WITH VALIDATION, ADD b INT'];
        yield 'alter modify with validation' => ['ALTER TABLE t1 WITH VALIDATION, MODIFY a BIGINT'];
        yield 'alter rename with validation' => ['ALTER TABLE t1 WITH VALIDATION, RENAME COLUMN a TO b'];
        yield 'alter rebuild validation before missing table' => ['ALTER TABLE missing WITH VALIDATION, REBUILD PARTITION ALL'];
        yield 'alter validation on existing table' => ['ALTER TABLE t1 WITH VALIDATION, FORCE'];
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
        yield 'table unknown engine' => ['CREATE TABLE x(a INT) ENGINE=absent'];
        yield 'table substituted engine' => ["SET sql_mode=''; CREATE TABLE x(a INT) ENGINE=absent"];
        yield 'table unsupported primary attribute' => ["CREATE TABLE x(a INT) ENGINE_ATTRIBUTE '{}'"];
        yield 'table secondary attribute' => ["CREATE TABLE x(a INT SECONDARY_ENGINE_ATTRIBUTE '{}')"];
        yield 'table relative data directory' => ["CREATE TABLE x(a INT) DATA DIRECTORY 'text'"];
        yield 'table relative index directory' => ["CREATE TABLE x(a INT) INDEX DIRECTORY 'text'"];
        yield 'table compression rejection' => ["CREATE TABLE x(a INT) COMPRESSION='text'"];
        yield 'table encryption rejection' => ["CREATE TABLE x(a INT) ENCRYPTION='text'"];
        yield 'table row format rejection' => ['CREATE TABLE x(a INT) ROW_FORMAT=FIXED'];
        yield 'table row format nonstrict' => ['SET innodb_strict_mode=0; CREATE TABLE x(a INT) ROW_FORMAT=FIXED'];
        yield 'table autoextend minimum' => ['CREATE TABLE x(a INT) AUTOEXTEND_SIZE=1'];
        yield 'table autoextend nonmultiple' => ['CREATE TABLE x(a INT) AUTOEXTEND_SIZE=4194305'];
        yield 'table missing tablespace' => ['CREATE TABLE x(a INT) TABLESPACE absent'];
        yield 'load charset before missing table' => ["LOAD DATA INFILE 'text' INTO TABLE missing CHARSET missing"];
        yield 'load charset before local file refusal' => ["LOAD DATA LOCAL INFILE 'text' INTO TABLE missing CHARSET missing"];
        yield 'load compression without bulk' => ["LOAD DATA INFILE 'text' INTO TABLE missing COMPRESSION='text'"];
        yield 'handler missing before default' => ['HANDLER missing READ k = (DEFAULT)'];
        yield 'handler index default' => ['CREATE TABLE x(a INT DEFAULT 2, KEY k(a)); INSERT INTO x VALUES(1),(2); HANDLER x OPEN; HANDLER x READ k = (DEFAULT)'];
        yield 'handler missing default' => ['CREATE TABLE x(a INT NOT NULL, KEY k(a)); HANDLER x OPEN; HANDLER x READ k = (DEFAULT)'];
        yield 'handler missing key before default' => ['CREATE TABLE x(a INT DEFAULT 2, KEY k(a)); HANDLER x OPEN; HANDLER x READ absent = (DEFAULT)'];
        yield 'kill named window' => ['KILL ROW_NUMBER() OVER missing'];
        yield 'kill named window before argument' => ['KILL SUM(missing) OVER absent'];
        yield 'kill inline window before argument' => ['KILL SUM(missing) OVER ()'];
        yield 'kill inline window before frame' => ['KILL ROW_NUMBER() OVER (ROWS UNBOUNDED PRECEDING EXCLUDE CURRENT ROW)'];
        yield 'kill inline window before inherited name' => ['KILL ROW_NUMBER() OVER (missing)'];
        yield 'window field resolution before exclusion' => ['SELECT ROW_NUMBER() OVER (ROWS UNBOUNDED PRECEDING EXCLUDE CURRENT ROW),missing'];
        yield 'window placement before exclusion' => ['SELECT 1 WHERE ROW_NUMBER() OVER (ROWS UNBOUNDED PRECEDING EXCLUDE CURRENT ROW)'];
        yield 'window placement before argument' => ['SELECT 1 WHERE SUM(missing) OVER ()'];
        yield 'engine attribute on column' => ["CREATE TABLE x(a INT ENGINE_ATTRIBUTE 'text')"];
        yield 'engine attribute on table' => ["CREATE TABLE x(a INT) ENGINE_ATTRIBUTE 'text'"];
        yield 'engine attribute on index' => ["CREATE INDEX i ON missing(a) SECONDARY_ENGINE_ATTRIBUTE 'text'"];
        yield 'engine attribute on tablespace' => ["ALTER TABLESPACE missing ENGINE_ATTRIBUTE 'text'"];
        yield 'engine attribute trailing JSON value' => ["CREATE TABLE x(a INT) ENGINE_ATTRIBUTE 'falseX'"];
        yield 'nonspatial column SRID' => ['CREATE TABLE x(a INT SRID 4326)'];
        yield 'non temporal timestamp default' => ['CREATE TABLE x(a INT DEFAULT CURRENT_TIMESTAMP)'];
        yield 'timestamp default precision mismatch' => ['CREATE TABLE x(a DATETIME(3) DEFAULT CURRENT_TIMESTAMP(2))'];
        yield 'non temporal timestamp update' => ['CREATE TABLE x(a INT ON UPDATE CURRENT_TIMESTAMP)'];
        yield 'timestamp update precision mismatch' => ['CREATE TABLE x(a TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(2))'];
        yield 'enum radix member character set' => ["CREATE TABLE x(a ENUM(0xe9,'b ') CHARACTER SET latin1); INSERT INTO x VALUES(1),(2); SELECT a,HEX(a),a+0 FROM x"];
        yield 'enum duplicates by collation' => ["CREATE TABLE x(a ENUM('a','A'))"];
        yield 'enum duplicates after trimming' => ["CREATE TABLE x(a ENUM('a ','a'))"];
        yield 'enum duplicates in definition order' => ["SET sql_mode=''; CREATE TABLE x(a ENUM('a','A','a'))"];
        yield 'enum binary collation' => ["CREATE TABLE x(a ENUM('a','A') CHARACTER SET utf8mb4 COLLATE utf8mb4_bin)"];
        yield 'set duplicate notes' => ["SET sql_mode=''; CREATE TABLE x(a SET('a','A','a'))"];
        yield 'enum return duplicates' => ['CREATE FUNCTION f() RETURNS ENUM(0x0f,0x0f) RETURN 1'];
        yield 'enum parameter duplicates' => ["CREATE PROCEDURE p(a ENUM('a','A')) SELECT a"];
        yield 'loadable function library' => ["CREATE FUNCTION memory_probe RETURNS STRING SONAME 'text'"];
        yield 'loadable aggregate library' => ["CREATE AGGREGATE FUNCTION memory_probe RETURNS REAL SONAME 'text'"];
        yield 'loadable function path' => ["CREATE FUNCTION memory_probe RETURNS STRING SONAME '/tmp/text'"];
        yield 'loadable function native name' => ["CREATE FUNCTION ABS RETURNS STRING SONAME 'text'"];
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
