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
final class StartedTableTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['SELECT 1', 'SELECT RAND(1,2)', 'SELECT @@unknown_variable', 'SELECT * FROM missing', 'SHOW WARNINGS', "BINLOG 'text'", 'INSERT INTO atomic_table VALUES(1)', 'ROLLBACK TO SAVEPOINT a', 'SET autocommit=1', 'COMMIT; SELECT * FROM atomic_table', 'ROLLBACK; SELECT * FROM atomic_table', 'COMMIT AND CHAIN; SELECT * FROM atomic_table', 'ROLLBACK AND CHAIN; SELECT * FROM atomic_table'] as $sql) {
            yield $sql => ['CREATE TABLE atomic_table(a INT) START TRANSACTION; ' . $sql];
        }
        foreach (['ENGINE=MEMORY START TRANSACTION', 'ENGINE=MyISAM START TRANSACTION', 'ENGINE=bad START TRANSACTION', 'START TRANSACTION SELECT missing', 'ENGINE=MEMORY START TRANSACTION SELECT 1', 'START TRANSACTION START TRANSACTION; COMMIT; SELECT * FROM atomic_table'] as $options) {
            yield $options => ['CREATE TABLE atomic_table(a INT) ' . $options];
        }
        yield 'temporary' => ['CREATE TEMPORARY TABLE atomic_table(a INT) START TRANSACTION'];
        yield 'temporary nontransactional engine' => ['CREATE TEMPORARY TABLE atomic_table(a INT) ENGINE=MEMORY START TRANSACTION'];
        yield 'foreign key before table lookup' => ['CREATE TABLE atomic_table(a INT, FOREIGN KEY(a) REFERENCES missing(a)) START TRANSACTION'];
        yield 'duplicate columns with unsupported engine' => ['CREATE TABLE atomic_table(a INT,a INT) ENGINE=MEMORY START TRANSACTION'];
        yield 'existing table still starts a transaction' => ['CREATE TABLE IF NOT EXISTS t1(a INT) START TRANSACTION; SELECT 1'];
        yield 'existing table survives rollback' => ['CREATE TABLE IF NOT EXISTS t1(a INT) START TRANSACTION; ROLLBACK; SELECT id FROM t1'];
        yield 'existing table invalid engine' => ['CREATE TABLE IF NOT EXISTS t1(a INT) ENGINE=MEMORY START TRANSACTION'];
        yield 'engine substitution' => ["SET sql_mode=''; CREATE TABLE atomic_table(a INT) ENGINE=bad START TRANSACTION; COMMIT; SELECT * FROM atomic_table"];
        yield 'autocommit off' => ['SET autocommit=0; CREATE TABLE atomic_table(a INT) START TRANSACTION; COMMIT; SELECT * FROM atomic_table'];
        yield 'previous writes commit' => ['BEGIN; INSERT INTO t1(id) VALUES(20); CREATE TABLE atomic_table(a INT) START TRANSACTION; ROLLBACK; SELECT id FROM t1'];
    }

    #[DataProvider('providerStatements')]
    public function testStartedTableMatchesTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);
        self::assertFalse($comparison->volatile);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
