<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\RoutineLocks;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoutineLocks::class)]
#[Small]
final class RoutineLocksTest extends TestCase
{
    #[DataProvider('providerCreation')]
    public function testCreateRefusesLocksBeforeRoutineSafetyAndExistence(string $version, string $kind, string $body): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); LOCK TABLE t WRITE; ROLLBACK');
        $error = $session->run('CREATE DEFINER=absent@localhost ' . $kind . ' d.f() ' . $body)[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1192, $error->getCode());
        self::assertSame([['Note', 1449, "The user specified as a definer ('absent'@'localhost') does not exist"], ['Error', 1192, "Can't execute the given command because you have active locked tables or an active transaction"], ['Error', 1307, 'Failed to CREATE ' . $kind . ' f']], $session->diagnostics->conditions);
    }

    #[DataProvider('providerRoutineKinds')]
    public function testAlterRetainsTheReleaseSpecificRoutineName(string $version, string $kind): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); LOCK TABLE t READ');
        $error = $session->run('ALTER ' . $kind . " absent COMMENT 'changed'")[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1192, $error->getCode());
        self::assertSame([['Error', 1192, "Can't execute the given command because you have active locked tables or an active transaction"], ['Error', 1334, 'Failed to ALTER ' . $kind . ' ' . (str_starts_with($version, '5.') ? 'd.' : '') . 'absent']], $session->diagnostics->conditions);
    }

    /**
     * @param list<array{string, int}> $conditions
     */
    #[DataProvider('providerDropping')]
    public function testDropRetainsTheOrderedRevocationConditions(string $version, string $lock, array $conditions): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); ' . $lock);
        $error = $session->run('DROP FUNCTION IF EXISTS absent')[0];
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1192, $error->getCode());
        self::assertSame($conditions, array_map(static fn (array $condition): array => [$condition[0], $condition[1]], $session->diagnostics->conditions));
    }

    #[DataProvider('providerCreation')]
    public function testCreateChecksMissingDatabasesFirstInMySql56(string $version, string $kind, string $body): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); LOCK TABLE t WRITE');
        $error = $session->run('CREATE DEFINER=absent@localhost ' . $kind . ' missing_db.f() ' . $body)[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame($version === '5.6.51' ? 1049 : 1192, $error->getCode());
        self::assertSame($version === '5.6.51' ? [1049] : [1449, 1192, 1307], array_column($session->diagnostics->conditions, 1));
    }

    /**
     * @param list<array{string, int}> $conditions
     */
    #[DataProvider('providerDropping')]
    public function testFailedDropRevokesLegacyGrantsOnlyWhenAllGrantTablesAreWritable(string $version, string $lock, array $conditions): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); CREATE USER u; CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 1; GRANT EXECUTE ON FUNCTION f TO u');
        $session->query($lock);
        $error = $session->run('DROP FUNCTION f')[0];
        $session->query('UNLOCK TABLES');
        $grants = $session->query('SHOW GRANTS FOR u')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1192, $error->getCode());
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $grants);
        self::assertCount(in_array(['Error', 1306], $conditions, true) ? 1 : 2, $grants->rows);
        $result = $session->query('SELECT f()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerCreation(): iterable
    {
        foreach (self::providerRoutineKinds() as $key => [$version, $kind]) {
            yield $key => [$version, $kind, $kind === 'FUNCTION' ? 'RETURNS INT RETURN 1' : 'BEGIN END'];
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRoutineKinds(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['FUNCTION', 'PROCEDURE'] as $kind) {
                yield $version . ':' . $kind => [$version, $kind];
            }
        }
    }

    /**
     * @return iterable<string, array{string, string, list<array{string, int}>}>
     */
    public static function providerDropping(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            $tables = ['user', 'db', 'tables_priv', 'columns_priv', 'procs_priv', 'proxies_priv'];
            if (!str_starts_with($version, '5.')) {
                array_push($tables, 'role_edges', 'default_roles', 'global_grants', 'password_history');
            }
            foreach ([
                'LOCK TABLE t WRITE' => [['Error', 1192], ['Error', 1100], ['Warning', 1405]],
                'LOCK TABLE mysql.user WRITE' => [['Error', 1192], ['Error', 1100], ['Warning', 1405]],
                'LOCK TABLE ' . implode(', ', array_map(static fn (string $name): string => 'mysql.' . $name . ' READ', $tables)) => [['Error', 1192], ['Error', 1099], ['Warning', 1405]],
                'LOCK TABLE ' . implode(', ', array_map(static fn (string $name): string => 'mysql.' . $name . ' WRITE', $tables)) => [['Error', 1192], str_starts_with($version, '5.') ? ['Error', 1306] : ['Warning', 1405]],
            ] as $lock => $conditions) {
                yield $version . ':' . $lock => [$version, $lock, $conditions];
            }
        }
    }
}
