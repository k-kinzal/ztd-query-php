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
final class TableClockTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = 'SET timestamp=1700000000; CREATE TABLE clocked(a INT PRIMARY KEY); ';
        $inspect = "SELECT CREATE_TIME=FROM_UNIXTIME(1700000000) AS original_creation, UPDATE_TIME IS NULL AS unchanged FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clocked'; ";
        $legacy = str_starts_with((string) getenv('MYSQL_VERSION'), '5.');
        $uncached = $legacy ? '' : 'SET information_schema_stats_expiry=0; ';
        yield 'creation survives a different statement clock' => [$setup . 'SET timestamp=1800000000; ' . $inspect];
        yield 'creation follows the reader time zone' => [$setup . "SET time_zone='+09:00'; " . $inspect];
        yield 'updates follow the reader time zone' => [$setup . $uncached . "INSERT INTO clocked VALUES(1); SET @updated=(SELECT UPDATE_TIME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clocked'); SET time_zone='+09:00'; SELECT UPDATE_TIME=DATE_ADD(@updated, INTERVAL 9 HOUR) AS shifted FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clocked'"];
        yield 'a rolled back write has no update time' => [$setup . 'BEGIN; INSERT INTO clocked VALUES(1); ' . $inspect . 'ROLLBACK; ' . $inspect];
        yield 'commit uses the actual clock' => [$setup . 'SET @before=SYSDATE(); BEGIN; INSERT INTO clocked VALUES(1); COMMIT; ' . "SELECT UPDATE_TIME BETWEEN @before AND SYSDATE() AS committed_now FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clocked'"];
        yield 'prepared XA commit uses the actual clock' => [$setup . "SET @before=SYSDATE(); XA START 'clock_xa'; INSERT INTO clocked VALUES(1); XA END 'clock_xa'; XA PREPARE 'clock_xa'; XA COMMIT 'clock_xa'; SELECT UPDATE_TIME BETWEEN @before AND SYSDATE() AS committed_now FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clocked'"];
        yield 'commit after sleep uses the same clock as SYSDATE' => [$setup . 'DO SLEEP(2); SET @before=SYSDATE(); BEGIN; INSERT INTO clocked VALUES(1); COMMIT; ' . "SELECT UPDATE_TIME BETWEEN @before AND SYSDATE() AS committed_now FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clocked'"];
        yield 'prepared XA commit after sleep uses the same clock as SYSDATE' => [$setup . "DO SLEEP(2); SET @before=SYSDATE(); XA START 'clock_xa'; INSERT INTO clocked VALUES(1); XA END 'clock_xa'; XA PREPARE 'clock_xa'; XA COMMIT 'clock_xa'; SELECT UPDATE_TIME BETWEEN @before AND SYSDATE() AS committed_now FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clocked'"];
        yield 'truncate preserves creation and clears updates' => [$setup . 'INSERT INTO clocked VALUES(1); SET timestamp=1800000000; TRUNCATE clocked; ' . $inspect];
        yield 'alter resets creation and updates' => [$setup . 'INSERT INTO clocked VALUES(1); SET timestamp=1800000000; ALTER TABLE clocked ADD b INT; ' . $inspect];
        yield 'uncached reads observe commit' => [$setup . $uncached . $inspect . 'BEGIN; INSERT INTO clocked VALUES(1); ' . $inspect . 'COMMIT; ' . $inspect];
        yield 'savepoint rollback leaves no committed write' => [$setup . $uncached . 'BEGIN; SAVEPOINT s; INSERT INTO clocked VALUES(1); ROLLBACK TO s; COMMIT; ' . $inspect];
        yield 'delete keeps the last update on an empty table' => [$setup . $uncached . 'INSERT INTO clocked VALUES(1); DELETE FROM clocked; ' . $inspect];
        yield 'cache expires with the session clock' => [$setup . $inspect . 'INSERT INTO clocked VALUES(1); ' . $inspect . 'SET timestamp=1700086401; ' . $inspect];
        if (!$legacy) {
            yield 'cache bypass retains the previous cached value' => [$setup . $inspect . 'INSERT INTO clocked VALUES(1); ' . $uncached . $inspect . 'SET information_schema_stats_expiry=86400; ' . $inspect];
        }
        yield 'table copies have their own creation time' => [$setup . 'SET timestamp=1800000000; CREATE TABLE copied LIKE clocked; ' . "SELECT CREATE_TIME=FROM_UNIXTIME(1800000000) AS created_later FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='copied'"];
        yield 'create select writes rows at creation' => ['SET timestamp=1700000000; CREATE TABLE clocked AS SELECT 1 a; ' . $inspect];
        yield 'empty create select has no committed write' => ['SET timestamp=1700000000; CREATE TABLE clocked AS SELECT 1 a FROM DUAL WHERE FALSE; ' . $inspect];
        yield 'MyISAM records creation as an update' => ['SET timestamp=1700000000; CREATE TABLE clocked(a INT) ENGINE=MyISAM; ' . $inspect];
        yield 'a partitioned table records its commit' => ['SET timestamp=1700000000; CREATE TABLE clocked(a INT) PARTITION BY HASH(a) PARTITIONS 2; INSERT INTO clocked VALUES(1); ' . $inspect];
        foreach (["COMMENT='changed'", 'ADD KEY(a)', 'ALTER COLUMN a SET DEFAULT 2', 'MODIFY a BIGINT', 'ENGINE=InnoDB', 'AUTO_INCREMENT=100', 'ROW_FORMAT=DYNAMIC', 'ALGORITHM=COPY', 'ADD b INT, ALGORITHM=INPLACE'] as $alter) {
            yield 'alter update clock: ' . $alter => [$setup . 'INSERT INTO clocked VALUES(1); SET timestamp=1800000000; ALTER TABLE clocked ' . $alter . '; ' . $inspect];
        }
    }

    #[DataProvider('providerStatements')]
    public function testTableClocksMatchTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
