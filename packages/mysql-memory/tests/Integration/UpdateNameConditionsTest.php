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
use SqlSemantics\Platform\MySql\Statement\Dml\Update;

#[CoversNothing]
#[Medium]
final class UpdateNameConditionsTest extends TestCase
{
    #[DataProvider('providerInputs')]
    public function testCompleteRequestsOrderDatabaseAndNameConditions(string $version, ?string $database, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: $database);
        $statement = $session->analyze($sql);
        self::assertInstanceOf(Update::class, $statement);
        self::assertSame($sql, $statement->toString());
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new Execution($session))->statement($statement);
    }

    #[DataProvider('providerReleases')]
    public function testExistingTablesPreserveTheNameCollision(string $version): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: 'pbt');
        $session->query('CREATE TABLE t(a INT); CREATE TABLE u(a INT)');
        $statement = $session->analyze('UPDATE t AS x, u AS x SET a = 1');
        self::assertInstanceOf(Update::class, $statement);
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        (new Execution($session))->statement($statement);
    }

    #[DataProvider('providerReleases')]
    public function testPreparationChecksNamesBeforeMissingTables(string $version): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: 'pbt');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        $session->query("PREPARE st FROM 'UPDATE t AS x,u AS x SET a=?'");
    }

    #[DataProvider('providerReleases')]
    public function testStoredDefinitionsCheckNamesBeforeInvocation(string $version): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: 'pbt');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        $session->query('CREATE PROCEDURE p() IF @run THEN UPDATE t AS x,u AS x SET a=1; END IF');
    }

    /**
     * @return iterable<string, array{string, string|null, string, int}>
     */
    public static function providerInputs(): iterable
    {
        foreach (self::providerReleases() as $name => [$version]) {
            foreach ([null, 'pbt'] as $database) {
                foreach ([
                    ['UPDATE t, t SET a = 1', $database === null ? 1046 : 1066],
                    ['UPDATE pbt.t, pbt.t SET a = 1', 1066],
                    ['UPDATE pbt.t AS x, pbt.u AS x SET a = 1', 1066],
                    ['UPDATE pbt.t AS x, (SELECT *) AS x SET a = 1', 1146],
                    ['UPDATE pbt.t AS x, (SELECT * FROM pbt.u, pbt.u) AS q SET a = 1', 1066],
                ] as $index => [$sql, $code]) {
                    yield $name . ' ' . ($database ?? 'none') . ' ' . $index => [$version, $database, $sql, $code];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() === 'mysql') {
                yield $release->value => [substr($release->value, 6)];
            }
        }
    }
}
