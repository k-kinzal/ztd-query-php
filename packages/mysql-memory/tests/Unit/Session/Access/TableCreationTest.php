<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\TableCreation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableCreation::class)]
#[Small]
final class TableCreationTest extends TestCase
{
    #[DataProvider('providerUnlockedCreations')]
    public function testLocksPrecedeDestinationAndDefinitionValidation(string $version, string $lock, string $sql, string $name, bool $prepared): void
    {
        $session = (new Instance($version, databases: ['test']))->connect(database: 'test');
        $session->query('CREATE TABLE t (id INT); CREATE TABLE u (id INT); LOCK TABLES ' . $lock);
        $replies = $session->run($prepared ? "PREPARE p FROM '" . $sql . "'" : $sql);

        self::assertInstanceOf(SqlError::class, $replies[0]);
        self::assertSame(1100, $replies[0]->getCode());
        self::assertSame("Table '$name' was not locked with LOCK TABLES", $replies[0]->getMessage());
        self::assertNull($session->instance->dictionary->table('test', 'absent'));
    }

    /**
     * @return iterable<string, array{string, string, string, string, bool}>
     */
    public static function providerUnlockedCreations(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach ([
                ['t READ', 'CREATE TABLE absent (id INT)', 'absent'],
                ['t WRITE', 'CREATE TABLE absent (id INT)', 'absent'],
                ['t AS x WRITE', 'CREATE TABLE t (id INT)', 't'],
                ['t READ', 'CREATE TABLE u (id INT)', 'u'],
                ['t READ', 'CREATE TABLE missing.t (id INT)', 't'],
                ['t READ', 'CREATE TABLE absent (id INT, id INT)', 'absent'],
                ['t READ', 'CREATE TABLE absent (id VARCHAR(99999))', 'absent'],
                ['t READ', 'CREATE TABLE absent (id INT NULL PRIMARY KEY)', 'absent'],
                ['t READ', 'CREATE TABLE absent SELECT * FROM t', 'absent'],
                ['t READ', 'CREATE TABLE absent LIKE missing', 'absent'],
                ['t READ', 'CREATE TABLE IF NOT EXISTS t LIKE missing', 'missing'],
                ['t AS x WRITE', 'CREATE TEMPORARY TABLE absent LIKE t', 't'],
            ] as [$lock, $sql, $name]) {
                foreach ([false, true] as $prepared) {
                    yield $version . '/' . $lock . '/' . $sql . '/' . ($prepared ? 'prepared' : 'direct') => [$version, $lock, $sql, $name, $prepared];
                }
            }
        }
    }

    public function testLocksDoNotTreatATemporaryShadowAsAPermanentTargetLock(): void
    {
        $session = (new Instance('8.4.7', databases: ['test']))->connect(database: 'test');
        $session->query('CREATE TABLE t (id INT); LOCK TABLES t READ; CREATE TEMPORARY TABLE absent (id INT)');

        $this->expectExceptionCode(1100);
        $session->query('CREATE TABLE absent (id INT)');
    }

    public function testLocksAllowTemporaryCreationAndExistingReadLockedDestinations(): void
    {
        $session = (new Instance('8.4.7', databases: ['test']))->connect(database: 'test');
        $session->query('CREATE TABLE t (id INT); LOCK TABLES t READ; CREATE TABLE IF NOT EXISTS t (id INT)');
        self::assertSame([['Note', 1050, "Table 't' already exists"]], $session->diagnostics->conditions);

        $session->query('CREATE TEMPORARY TABLE a (id INT); CREATE TEMPORARY TABLE b LIKE t; CREATE TEMPORARY TABLE c SELECT * FROM t');
        self::assertNotNull($session->temporaries->table('test', 'a'));
        self::assertNotNull($session->temporaries->table('test', 'b'));
        self::assertNotNull($session->temporaries->table('test', 'c'));
    }

    public function testLocksFollowStorageEngineResolution(): void
    {
        $session = (new Instance('8.4.7', databases: ['test']))->connect(database: 'test');
        $session->query('CREATE TABLE t (id INT); LOCK TABLES t READ');

        $this->expectExceptionCode(1286);
        $session->query('CREATE TABLE absent (id INT) ENGINE=missing');
    }

    public function testDatabasePrecedesLegacyDefinitionConditions(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $session->query('CREATE TABLE t(a INT) CHARACTER SET missing');
    }

    public function testDatabaseLeavesModernDefinitionConditionsFirst(): void
    {
        $session = (new Instance('8.4.7'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1115);
        $session->query('CREATE TABLE t(a INT) CHARACTER SET missing');
    }

    public function testDatabasePrecedesLegacyConversionNameLookups(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $session->query('ALTER TABLE t CONVERT TO CHARSET missing');
    }

    public function testCheckRefusesStatementsUntilTheTransactionEnds(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT) START TRANSACTION');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3977);
        $session->query('SELECT 1');
    }

    public function testCheckLeavesParseTimeErrorsAheadOfTheRestriction(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT) START TRANSACTION');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1582);
        $session->query('SELECT RAND(1,2)');
    }

    public function testRequestedRecognizesTheTableOption(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT) START TRANSACTION START TRANSACTION');
        self::assertTrue($session->transaction->creation->active);
    }

    public function testValidateRejectsNonAtomicEnginesBeforeDuplicateColumns(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3979);
        $this->expectExceptionMessage('START TRANSACTION clause cannot be used with engine that does not support atomic DDL.');
        $session->query('CREATE TABLE d.t(a INT,a INT) ENGINE=MEMORY START TRANSACTION');
    }

    public function testValidateRejectsForeignKeysBeforeLookingUpReferencedTables(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3978);
        $session->query('CREATE TABLE d.t(a INT, FOREIGN KEY(a) REFERENCES d.missing(a)) START TRANSACTION');
    }
}
