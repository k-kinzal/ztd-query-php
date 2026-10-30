<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\AccountTables;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountTables::class)]
#[Small]
final class AccountTablesTest extends TestCase
{
    #[DataProvider('providerAccountChanges')]
    public function testCheckRequiresTheGrantTablesBeforeAccountLookup(string $version, string $sql, string $lock, int $code, string $message): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); ' . $lock . '; ROLLBACK');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage($message);
        $session->query($sql);
    }

    /**
     * @return iterable<string, array{string, string, string, int, string}>
     */
    public static function providerAccountChanges(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            $commands = ["CREATE USER 'u'", "DROP USER 'absent'", "RENAME USER 'absent' TO 'new'", "SET PASSWORD FOR CURRENT_USER = 'text'", "GRANT SELECT ON *.* TO 'u'", "REVOKE SELECT ON *.* FROM 'u'", 'GRANT PROXY ON CURRENT_USER TO CURRENT_USER', 'REVOKE PROXY ON CURRENT_USER FROM CURRENT_USER', "REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'absent'"];
            if ($version !== '5.6.51') {
                $commands[] = 'ALTER USER CURRENT_USER ACCOUNT UNLOCK';
            }
            if (!str_starts_with($version, '5.')) {
                array_push($commands, "CREATE ROLE 'r'", "DROP ROLE 'absent'", "GRANT 'absent' TO CURRENT_USER", "REVOKE 'absent' FROM CURRENT_USER", "SET DEFAULT ROLE NONE TO 'u'", 'ALTER USER CURRENT_USER DEFAULT ROLE NONE');
            }
            $tables = ['user', 'db', 'tables_priv', 'columns_priv', 'procs_priv', 'proxies_priv'];
            if (!str_starts_with($version, '5.')) {
                array_push($tables, 'role_edges', 'default_roles', 'global_grants', 'password_history');
            }
            $locks = [
                'LOCK TABLE t WRITE' => [1100, "Table 'user' was not locked with LOCK TABLES"],
                'LOCK TABLE mysql.user READ' => [1100, "Table 'db' was not locked with LOCK TABLES"],
                'LOCK TABLE ' . implode(', ', array_map(static fn (string $table): string => 'mysql.' . $table . ' READ', $tables)) => [1099, "Table 'user' was locked with a READ lock and can't be updated"],
            ];
            foreach ($commands as $sql) {
                foreach ($locks as $lock => [$code, $message]) {
                    if (str_starts_with($version, '5.') && $lock === 'LOCK TABLE mysql.user READ') {
                        if (str_starts_with($sql, 'SET PASSWORD') || str_starts_with($sql, 'ALTER USER')) {
                            [$code, $message] = [1099, "Table 'user' was locked with a READ lock and can't be updated"];
                        } elseif (str_contains($sql, 'PROXY')) {
                            $message = "Table 'proxies_priv' was not locked with LOCK TABLES";
                        }
                    }
                    yield $version . ':' . $sql . ':' . $lock => [$version, $sql, $lock, $code, $message];
                }
            }
        }
    }

    #[DataProvider('providerPrivilegeLevels')]
    public function testPrivilegesChecksTheTablesUsedAtEachLegacyLevel(string $version, string $sql, string $message): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); CREATE FUNCTION f() RETURNS INT DETERMINISTIC RETURN 1; LOCK TABLE mysql.user READ');
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage($message);
        $session->query($sql);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerPrivilegeLevels(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $version) {
            foreach ([
                "GRANT SELECT ON *.* TO 'u'" => "Table 'db' was not locked with LOCK TABLES",
                "GRANT SELECT ON d.* TO 'u'" => "Table 'db' was not locked with LOCK TABLES",
                "GRANT SELECT ON d.t TO 'u'" => "Table 'tables_priv' was not locked with LOCK TABLES",
                "REVOKE SELECT ON d.t FROM 'u'" => "Table 'tables_priv' was not locked with LOCK TABLES",
                "GRANT EXECUTE ON FUNCTION d.f TO 'u'" => "Table 'procs_priv' was not locked with LOCK TABLES",
                "REVOKE EXECUTE ON FUNCTION d.f FROM 'u'" => "Table 'procs_priv' was not locked with LOCK TABLES",
                "GRANT EXECUTE ON FUNCTION d.absent TO 'u'" => $version === '5.6.51' ? 'FUNCTION or PROCEDURE absent does not exist' : 'FUNCTION absent does not exist',
            ] as $sql => $message) {
                yield $version . ':' . $sql => [$version, $sql, $message];
            }
        }
    }

    public function testCheckLetsShowCreateUserReadOnlyTheUserTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('LOCK TABLE mysql.user READ');
        $this->expectExceptionCode(1396);
        $this->expectExceptionMessage("Operation SHOW CREATE USER failed for 'absent'@'%'");
        $session->query("SHOW CREATE USER 'absent'");
    }

    public function testCheckLeavesCachedRoleActivationAvailable(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); LOCK TABLE t WRITE');
        self::assertInstanceOf(Completion::class, $session->query('SET ROLE NONE')[0]);
    }

    #[DataProvider('providerGrantObjects')]
    public function testPrivilegesValidatesGrantObjectsBeforeGrantTableLocks(string $version, string $locks, string $sql, int $code, string $message): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT); CREATE USER u; LOCK TABLE ' . $locks);
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage($message);
        $session->query($sql);
    }

    /**
     * @return iterable<string, array{string, string, string, int, string}>
     */
    public static function providerGrantObjects(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            foreach (['t READ', 't WRITE', 'mysql.user WRITE'] as $locks) {
                foreach ([
                    'GRANT DELETE ON absent TO u' => [1146, "Table 'd.absent' doesn't exist"],
                    'GRANT DELETE ON missing_db.t TO u' => [1146, "Table 'missing_db.t' doesn't exist"],
                    'GRANT PROCESS ON absent TO u' => [1144, 'Illegal GRANT/REVOKE command'],
                    'GRANT EXECUTE ON FUNCTION absent TO u' => [1305, $version === '5.6.51' ? 'FUNCTION or PROCEDURE absent does not exist' : 'FUNCTION absent does not exist'],
                    'GRANT SELECT(id) ON absent TO u' => [1100, "Table 'absent' was not locked"],
                    'GRANT SELECT(absent) ON t TO u' => str_starts_with($locks, 't ') ? [1054, "Unknown column 'absent'"] : [1100, "Table 't' was not locked"],
                ] as $sql => [$code, $message]) {
                    yield $version . ':' . $locks . ':' . $sql => [$version, $locks, $sql, $code, $message];
                }
            }
        }
    }
}
