<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\Locks;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Locks::class)]
#[Small]
final class LocksTest extends TestCase
{
    #[DataProvider('providerExplainedWrites')]
    public function testCheckRequiresTheExplainedWriteLockAfterResolvingItsOperands(string $version, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); LOCK TABLES t READ');

        $this->expectExceptionCode($code);
        $session->query($sql);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerExplainedWrites(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            $prefixes = ['EXPLAIN ', 'EXPLAIN FORMAT=JSON '];
            if (!str_starts_with($version, '5.')) {
                $prefixes = [...$prefixes, 'EXPLAIN ANALYZE ', 'EXPLAIN ANALYZE FORMAT=JSON '];
            }
            foreach ($prefixes as $prefix) {
                foreach (['INSERT INTO t VALUES()' => 1099, 'REPLACE DELAYED t VALUES()' => 1099, 'INSERT INTO t SELECT 1' => 1099, 'INSERT INTO t SET id=1' => 1099, 'UPDATE t SET id=1' => 1099, 'DELETE FROM t' => 1099, 'DELETE t FROM t' => 1099, 'INSERT INTO t(missing) VALUES(1)' => 1054, 'INSERT INTO t VALUES(1,2)' => 1136, 'UPDATE t SET missing=1' => 1054] as $body => $code) {
                    if ($version === '5.6.51' && in_array($body, ['INSERT INTO t VALUES()', 'REPLACE DELAYED t VALUES()', 'INSERT INTO t SET id=1'], true)) {
                        continue;
                    }
                    yield $version . '/' . $prefix . $body => [$version, $prefix . $body, $code];
                }
            }
            yield $version . '/format priority' => [$version, 'EXPLAIN FORMAT=UNKNOWN INSERT INTO t VALUES(1)', 1791];
        }
    }

    #[DataProvider('providerPreparedExplanations')]
    public function testCheckAllowsPreparingAnExplainedWriteButRequiresItsLockOnExecution(string $version, string $body): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); LOCK TABLES t READ');
        $session->query("PREPARE p FROM 'EXPLAIN " . $body . "'");

        $this->expectExceptionCode(1099);
        $session->query('EXECUTE p');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerPreparedExplanations(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['INSERT INTO t VALUES(1)', 'UPDATE t SET id=1', 'DELETE FROM t'] as $body) {
                if ($version === '5.6.51' && $body === 'INSERT INTO t VALUES(1)') {
                    continue;
                }
                yield $version . '/' . $body => [$version, $body];
            }
        }
    }

    #[DataProvider('providerLegacyExplainedInserts')]
    public function testCheckAllowsExplainingLegacyInsertedValuesUnderAReadLock(string $sql): void
    {
        $session = (new Instance('5.6.51', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); LOCK TABLES t READ');

        self::assertInstanceOf(ResultSet::class, $session->query($sql)[0]);
    }

    #[DataProvider('providerLegacyExplainedInserts')]
    public function testCheckAllowsExecutingPreparedLegacyInsertExplanationsUnderAReadLock(string $sql): void
    {
        $session = (new Instance('5.6.51', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); LOCK TABLES t READ');
        $session->query("PREPARE p FROM '" . $sql . "'");

        self::assertInstanceOf(ResultSet::class, $session->query('EXECUTE p')[0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLegacyExplainedInserts(): iterable
    {
        foreach (['EXPLAIN ', 'EXPLAIN FORMAT=JSON '] as $prefix) {
            foreach (['INSERT INTO t VALUES()', 'INSERT INTO t VALUES(1)', 'REPLACE DELAYED t VALUES()', 'INSERT INTO t SET id=1'] as $body) {
                yield $prefix . $body => [$prefix . $body];
            }
        }
    }

    public function testMetadataFindsAWriteLockAfterAReadAlias(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); LOCK TABLES t AS x READ, t AS y WRITE');
        (new Locks())->metadata($session, 'd', 't');

        self::assertCount(2, $session->locks);
    }

    public function testMetadataDoesNotTreatATemporaryShadowAsAPersistentLock(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT); LOCK TABLES t AS x READ; CREATE TEMPORARY TABLE t(a INT)');

        $this->expectExceptionCode(1099);
        (new Locks())->metadata($session, 'd', 't');
    }

    public function testCheckRefusesATableThatIsNotLocked(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT); LOCK TABLES t READ');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1100);
        $this->expectExceptionMessage("Table 'u' was not locked with LOCK TABLES");

        $session->query('SELECT * FROM t, u');
    }

    public function testCheckRefusesAWriteUnderAReadLock(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); LOCK TABLES t READ');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1099);
        $this->expectExceptionMessage("Table 't' was locked with a READ lock and can't be updated");

        $session->query('INSERT INTO t VALUES (1)');
    }

    public function testCheckAllowsACommonTableExpression(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); LOCK TABLES t WRITE; INSERT INTO t VALUES (1)');

        $result = $session->query('WITH c AS (SELECT a FROM t) SELECT * FROM c')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testWrittenAnswersTheTablesOfAnUpdate(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('UPDATE t SET a = 1');

        self::assertCount(1, (new Locks())->written($statement));
    }

    public function testRequiredOpensEveryNameBeforeCheckingWriteLocks(): void
    {
        $session = (new Instance())->connect();
        $session->query('LOCK TABLE mysql.user READ');
        $this->expectExceptionCode(1100);
        $this->expectExceptionMessage("Table 'db' was not locked with LOCK TABLES");
        (new Locks())->required($session, [new QualifiedName(new Name('user'), new Name('mysql')), new QualifiedName(new Name('db'), new Name('mysql'))], true);
    }

    public function testAllowedRefusesATableUnderAnotherName(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); LOCK TABLES t READ');

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Table 'x' was not locked with LOCK TABLES");

        (new Locks())->allowed($session, new QualifiedName(new Name('t')), 'x', [], false);
    }

    #[DataProvider('providerOccurrences')]
    public function testOpenedRequiresOneLockPerReachedOccurrence(string $version, string $locks, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); CREATE TABLE u(id INT); LOCK TABLE ' . $locks);
        $replies = $session->run($sql);
        $reply = array_pop($replies);

        self::assertNotNull($reply);
        self::assertSame($code, $reply instanceof SqlError ? $reply->getCode() : 0);
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function providerOccurrences(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['SELECT * FROM t UNION ALL SELECT * FROM t' => 1100, 'SELECT (SELECT id FROM t) FROM t' => 1100, 'SELECT x FROM u' => 1100, 'SELECT * FROM missing' => 1100, 'SELECT ABS() FROM t' => 1582, 'SELECT x FROM t' => 1054, 'INSERT INTO t SELECT * FROM t' => 1100, 'UPDATE t SET id=(SELECT MAX(id) FROM t)' => 1100, 'DELETE FROM t WHERE id=(SELECT MAX(id) FROM t)' => 1100] as $sql => $code) {
                yield $version . ':' . $sql => [$version, 't WRITE', $sql, $code];
            }
            yield $version . ':separate aliases' => [$version, 't AS a READ, t AS b WRITE', 'SELECT * FROM t a JOIN t b USING(id)', 0];
            yield $version . ':missing alias' => [$version, 't READ', 'SELECT * FROM t a JOIN t b USING(id)', 1100];
            if (!str_starts_with($version, '5.')) {
                yield $version . ':explicit tables' => [$version, 't WRITE', 'TABLE t UNION ALL TABLE t', 1100];
                yield $version . ':repeated CTE' => [$version, 't WRITE', 'WITH c AS (SELECT * FROM t) SELECT * FROM c UNION ALL SELECT * FROM c', 1100];
                yield $version . ':unused CTE' => [$version, 't WRITE', 'WITH unused AS (SELECT * FROM u) SELECT 1', 0];
            }
        }
    }

    #[DataProvider('providerDetachedOccurrences')]
    public function testOpenedPrecedesDetachedStorageAndColumnResolution(string $sql, int $code): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); LOCK TABLE t WRITE; ALTER TABLE t DISCARD TABLESPACE');
        $replies = $session->run($sql);
        $reply = array_pop($replies);

        self::assertInstanceOf(SqlError::class, $reply);
        self::assertSame($code, $reply->getCode());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerDetachedOccurrences(): iterable
    {
        yield 'one handle' => ['SELECT * FROM t', 1814];
        yield 'repeated handle' => ['SELECT * FROM t UNION ALL SELECT * FROM t', 1100];
        yield 'explicit repeated handle' => ['TABLE t UNION ALL TABLE t', 1100];
        yield 'column resolution' => ['SELECT x FROM t', 1054];
        yield 'missing handle' => ['SELECT * FROM t, missing', 1100];
    }
}
