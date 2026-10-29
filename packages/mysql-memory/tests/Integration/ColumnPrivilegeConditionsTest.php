<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversNothing]
#[Medium]
final class ColumnPrivilegeConditionsTest extends TestCase
{
    #[DataProvider('providerTargets')]
    public function testCompleteRequestsExecuteWithoutSql(string $version, ?string $database, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: $database);
        $statement = $session->analyze($sql);
        self::assertSame($sql, $statement->toString());
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new Execution($session))->statement($statement);
    }

    #[DataProvider('providerUnqualifiedPreparation')]
    public function testPreparationRequiresTheTargetDatabaseBeforeRecipientChecks(string $version, string $sql): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect();
        $statement = $session->analyze($sql);
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        (new \MySqlMemory\Session\Access\Preparation())->check($statement, $session);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerUnqualifiedPreparation(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['GRANT' => 'TO', 'REVOKE' => 'FROM'] as $verb => $recipient) {
                foreach (['SELECT', 'SELECT (a)'] as $privilege) {
                    foreach (['*', 't'] as $level) {
                        foreach (['missing', "'" . str_repeat('u', 65) . "'"] as $user) {
                            $sql = $verb . ' ' . $privilege . ' ON ' . $level . ' ' . $recipient . ' ' . $user;
                            yield $release->value . ' ' . $sql => [substr($release->value, 6), $sql];
                        }
                    }
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string, ?string, string, int}>
     */
    public static function providerTargets(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
            foreach ([null, 'pbt'] as $database) {
                foreach ([['*', 'missing', $database === null ? 1046 : 1144], ['*', "'" . str_repeat('u', 65) . "'", $database === null ? 1046 : ($legacy ? 1144 : 1470)], ['*.*', 'missing', 1144], ['pbt.*', 'missing', 1144], ['pbt.*', "'" . str_repeat('u', 65) . "'", $legacy ? 1144 : 1470]] as $index => [$level, $user, $code]) {
                    foreach (['GRANT' => 'TO', 'REVOKE' => 'FROM'] as $verb => $recipient) {
                        yield $release->value . ' ' . ($database ?? 'none') . ' ' . $index . ' ' . $verb => [substr($release->value, 6), $database, $verb . ' SELECT (a) ON ' . $level . ' ' . $recipient . ' ' . $user, $code];
                    }
                }
            }
        }
    }
}
