<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Execution\Statements;

#[CoversClass(Statements::class)]
#[Medium]
final class StatementsTest extends TestCase
{
    public function testWriteLeavesQueriesForTheQuerySerializer(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertNull(Statements::write($query));
    }

    public function testTransactionsKeepDistributedCommitDistinctFromLocalCommit(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $local = $binder->bind('COMMIT');
        $distributed = $binder->bind("XA COMMIT 'g' ONE PHASE");
        self::assertNotNull(Statements::transactions($local));
        self::assertNotNull(Statements::transactions($distributed));
        self::assertSame('COMMIT', $local->toString());
        self::assertSame("XA COMMIT 'g' ONE PHASE", $distributed->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, string, string}>
     */
    public static function providerWriteRoutesEveryOperation(): iterable
    {
        return [
            'DO 1 (MySql)' => [Dialect::MySql, null, 'DO 1', 'DO 1'],
            'LOCK TABLE t (PostgreSql)' => [Dialect::PostgreSql, null, 'LOCK TABLE t', 'LOCK TABLE "public"."t" IN ACCESS EXCLUSIVE MODE'],
            'LOCK TABLES t READ (MySql)' => [Dialect::MySql, null, 'LOCK TABLES t READ', 'LOCK TABLES `t` READ'],
            'CHECKPOINT (PostgreSql)' => [Dialect::PostgreSql, null, 'CHECKPOINT', 'CHECKPOINT'],
            'RESTART (MySql)' => [Dialect::MySql, null, 'RESTART', 'RESTART'],
            'SHUTDOWN (MySql)' => [Dialect::MySql, null, 'SHUTDOWN', 'SHUTDOWN'],
            'UNLOCK TABLES (MySql)' => [Dialect::MySql, null, 'UNLOCK TABLES', 'UNLOCK TABLES'],
            'LISTEN ch (PostgreSql)' => [Dialect::PostgreSql, null, 'LISTEN ch', 'LISTEN "ch"'],
            'UNLISTEN ch (PostgreSql)' => [Dialect::PostgreSql, null, 'UNLISTEN ch', 'UNLISTEN "ch"'],
            'UNLISTEN * (PostgreSql)' => [Dialect::PostgreSql, null, 'UNLISTEN *', 'UNLISTEN *'],
            'NOTIFY ch (PostgreSql)' => [Dialect::PostgreSql, null, 'NOTIFY ch', 'NOTIFY "ch"'],
            'KILL 42 (MySql)' => [Dialect::MySql, null, 'KILL 42', 'KILL CONNECTION 42'],
            'KILL QUERY 42 (MySql)' => [Dialect::MySql, null, 'KILL QUERY 42', 'KILL QUERY 42'],
            'INSTALL PLUGIN p SONAME \'a.so\' (MySql)' => [Dialect::MySql, null, 'INSTALL PLUGIN p SONAME \'a.so\'', 'INSTALL PLUGIN `p` SONAME \'a.so\''],
            'UNINSTALL PLUGIN p (MySql)' => [Dialect::MySql, null, 'UNINSTALL PLUGIN p', 'UNINSTALL PLUGIN `p`'],
            'CLONE LOCAL DATA DIRECTORY = \'/tmp/x\' (MySql)' => [Dialect::MySql, null, 'CLONE LOCAL DATA DIRECTORY = \'/tmp/x\'', 'CLONE LOCAL DATA DIRECTORY \'/tmp/x\''],
            'BINLOG \'abc\' (MySql)' => [Dialect::MySql, null, 'BINLOG \'abc\'', 'BINLOG \'abc\''],
            'DISCARD ALL (PostgreSql)' => [Dialect::PostgreSql, null, 'DISCARD ALL', 'DISCARD ALL'],
            'SET CONSTRAINTS ALL DEFERRED (PostgreSql)' => [Dialect::PostgreSql, null, 'SET CONSTRAINTS ALL DEFERRED', 'SET CONSTRAINTS ALL DEFERRED'],
            'PREPARE p AS SELECT 1 (PostgreSql)' => [Dialect::PostgreSql, null, 'PREPARE p AS SELECT 1', 'PREPARE "p" AS SELECT 1'],
            'PREPARE p FROM \'SELECT 1\' (MySql)' => [Dialect::MySql, null, 'PREPARE p FROM \'SELECT 1\'', 'PREPARE `p` FROM \'SELECT 1\''],
            'EXECUTE p (PostgreSql)' => [Dialect::PostgreSql, null, 'EXECUTE p', 'EXECUTE "p"'],
            'EXECUTE p USING @a (MySql)' => [Dialect::MySql, null, 'EXECUTE p USING @a', 'EXECUTE `p` USING @`a`'],
            'DEALLOCATE p (PostgreSql)' => [Dialect::PostgreSql, null, 'DEALLOCATE p', 'DEALLOCATE "p"'],
            'DEALLOCATE ALL (PostgreSql)' => [Dialect::PostgreSql, null, 'DEALLOCATE ALL', 'DEALLOCATE ALL'],
            'DECLARE c CURSOR FOR SELECT 1 (PostgreSql)' => [Dialect::PostgreSql, null, 'DECLARE c CURSOR FOR SELECT 1', 'DECLARE "c" CURSOR FOR SELECT 1'],
            'FETCH c (PostgreSql)' => [Dialect::PostgreSql, null, 'FETCH c', 'FETCH NEXT FROM "c"'],
            'MOVE c (PostgreSql)' => [Dialect::PostgreSql, null, 'MOVE c', 'MOVE NEXT FROM "c"'],
            'CLOSE c (PostgreSql)' => [Dialect::PostgreSql, null, 'CLOSE c', 'CLOSE "c"'],
            'CLOSE ALL (PostgreSql)' => [Dialect::PostgreSql, null, 'CLOSE ALL', 'CLOSE ALL'],
            'EXPLAIN SELECT 1 (MySql)' => [Dialect::MySql, null, 'EXPLAIN SELECT 1', 'EXPLAIN SELECT 1'],
            'EXPLAIN FOR CONNECTION 1 (MySql)' => [Dialect::MySql, null, 'EXPLAIN FOR CONNECTION 1', 'EXPLAIN FOR CONNECTION 1'],
            'EXPLAIN FOR SCHEMA d SELECT 1 (MySql)' => [Dialect::MySql, null, 'EXPLAIN FOR SCHEMA d SELECT 1', 'EXPLAIN FOR DATABASE `d` SELECT 1'],
            'XA START \'a\' (MySql)' => [Dialect::MySql, null, 'XA START \'a\'', 'XA START \'a\''],
            'XA END \'a\' (MySql)' => [Dialect::MySql, null, 'XA END \'a\'', 'XA END \'a\''],
            'XA PREPARE \'a\' (MySql)' => [Dialect::MySql, null, 'XA PREPARE \'a\'', 'XA PREPARE \'a\''],
            'XA COMMIT \'a\' (MySql)' => [Dialect::MySql, null, 'XA COMMIT \'a\'', 'XA COMMIT \'a\''],
            'XA ROLLBACK \'a\' (MySql)' => [Dialect::MySql, null, 'XA ROLLBACK \'a\'', 'XA ROLLBACK \'a\''],
            'XA RECOVER (MySql)' => [Dialect::MySql, null, 'XA RECOVER', 'XA RECOVER'],
            'BEGIN (MySql)' => [Dialect::MySql, null, 'BEGIN', 'START TRANSACTION'],
            'COMMIT (MySql)' => [Dialect::MySql, null, 'COMMIT', 'COMMIT'],
            'ROLLBACK (MySql)' => [Dialect::MySql, null, 'ROLLBACK', 'ROLLBACK'],
            'SAVEPOINT s (MySql)' => [Dialect::MySql, null, 'SAVEPOINT s', 'SAVEPOINT `s`'],
            'RELEASE SAVEPOINT s (MySql)' => [Dialect::MySql, null, 'RELEASE SAVEPOINT s', 'RELEASE SAVEPOINT `s`'],
            'ROLLBACK TO s (MySql)' => [Dialect::MySql, null, 'ROLLBACK TO s', 'ROLLBACK TO SAVEPOINT `s`'],
            'PREPARE TRANSACTION \'x\' (PostgreSql)' => [Dialect::PostgreSql, null, 'PREPARE TRANSACTION \'x\'', 'PREPARE TRANSACTION \'x\''],
            'COMMIT PREPARED \'x\' (PostgreSql)' => [Dialect::PostgreSql, null, 'COMMIT PREPARED \'x\'', 'COMMIT PREPARED \'x\''],
            'ROLLBACK PREPARED \'x\' (PostgreSql)' => [Dialect::PostgreSql, null, 'ROLLBACK PREPARED \'x\'', 'ROLLBACK PREPARED \'x\''],
            'VACUUM (PostgreSql)' => [Dialect::PostgreSql, null, 'VACUUM', 'VACUUM'],
            'DO $$ BEGIN END $$ (PostgreSql)' => [Dialect::PostgreSql, null, 'DO $$ BEGIN END $$', 'DO $$ BEGIN END $$'],
            'CACHE INDEX t IN c (MySql)' => [Dialect::MySql, null, 'CACHE INDEX t IN c', 'CACHE INDEX `t` IN `c`'],
            'SHOW TABLES (MySql)' => [Dialect::MySql, null, 'SHOW TABLES', 'SHOW TABLES'],
            'FLUSH TABLES (MySql)' => [Dialect::MySql, null, 'FLUSH TABLES', 'FLUSH TABLES'],
            'HANDLER t OPEN (MySql)' => [Dialect::MySql, null, 'HANDLER t OPEN', 'HANDLER `t` OPEN'],
            'ANALYZE TABLE t (MySql)' => [Dialect::MySql, null, 'ANALYZE TABLE t', 'ANALYZE TABLE `t`'],
            'CHECKSUM TABLE t (MySql)' => [Dialect::MySql, null, 'CHECKSUM TABLE t', 'CHECKSUM TABLE `t`'],
            'SHOW ALL (PostgreSql)' => [Dialect::PostgreSql, null, 'SHOW ALL', 'SHOW ALL'],
            'SHOW CREATE TABLE t (MySql)' => [Dialect::MySql, null, 'SHOW CREATE TABLE t', 'SHOW CREATE TABLE `t`'],
            'SHOW VARIABLES (MySql)' => [Dialect::MySql, null, 'SHOW VARIABLES', 'SHOW VARIABLES'],
            'SELECT 1 INTO @a (MySql)' => [Dialect::MySql, null, 'SELECT 1 INTO @a', 'SELECT 1 INTO @`a`'],
        ];
    }

    #[DataProvider('providerWriteRoutesEveryOperation')]
    public function testWriteRoutesEveryOperation(Dialect $dialect, ?string $version, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build()))->bind($sql, strict: false);
        self::assertSame($expected, Statements::write($statement)?->toString());
    }

    public function testWriteLeavesTableStatementsForTheQuerySerializer(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('TABLE t', strict: false);
        self::assertNull(Statements::write($statement));
    }
}
