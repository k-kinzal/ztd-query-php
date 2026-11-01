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

#[CoversNothing]
#[Medium]
final class DeclaredColumnSizeConditionsTest extends TestCase
{
    #[DataProvider('providerStatements')]
    public function testExecutionRefusesTheRetainedSizeWithoutReadingSql(string $version, string $sql, int $code): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: 'pbt');
        $statement = $session->analyze($sql);
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new Execution($session))->statement($statement);
    }

    #[DataProvider('providerReleases')]
    public function testExecutionResolvesTheAlterTargetDatabaseBeforeTheTypeSize(string $version): void
    {
        $session = (new Instance($version))->connect();
        $statement = $session->analyze('ALTER TABLE t MODIFY c FLOAT(0,0)');
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        (new Execution($session))->statement($statement);
    }

    #[DataProvider('providerReleases')]
    public function testPreparationResolvesTheAlterTargetDatabaseBeforeTheTypeSize(string $version): void
    {
        $session = (new Instance($version))->connect();
        $statement = $session->analyze('ALTER TABLE t MODIFY c FLOAT(0,0)');
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        (new \MySqlMemory\Session\Access\Preparation())->check($statement, $session);
    }

    #[DataProvider('providerReleases')]
    public function testPartialInputRetainsTheReleaseSpecificColumnCompletionBoundary(string $version): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: 'pbt');
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(str_starts_with($version, '5.') ? 1439 : 1064);
        $session->query('ALTER TABLE t MODIFY c FLOAT(0,0), ?');
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerStatements(): iterable
    {
        foreach (self::providerReleases() as [$version]) {
            foreach (['FLOAT(0,0)' => 1439, 'FLOAT(256,31)' => 1425, 'BIT(65)' => 1439, 'TIME(7)' => 1426] as $type => $code) {
                foreach (['CREATE TABLE t(c ' . $type . ')', 'ALTER TABLE t MODIFY c ' . $type] as $body) {
                    yield $version . ' ' . $body => [$version, $body, $code];
                    yield $version . ' stored ' . $body => [$version, 'CREATE PROCEDURE p() BEGIN IF 0 THEN ' . $body . '; END IF; END', $code];
                }
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version => [$version];
        }
    }
}
