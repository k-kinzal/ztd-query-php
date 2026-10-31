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
final class RepeatedDropTargetsTest extends TestCase
{
    #[DataProvider('providerStatements')]
    public function testExecutionRefusesRepeatedTargetsWithoutReadingSql(string $version, string $sql): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect();
        $statement = $session->analyze($sql);
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1066);
        (new Execution($session))->statement($statement);
    }

    #[DataProvider('providerReleases')]
    public function testExecutionRequiresAnEarlierTargetsDefaultDatabase(string $version): void
    {
        $session = (new Instance($version))->connect();
        $statement = $session->analyze('DROP TABLE t, t');
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        (new Execution($session))->statement($statement);
    }

    #[DataProvider('providerReleases')]
    public function testPreparationRequiresAnEarlierTargetsDefaultDatabase(string $version): void
    {
        $session = (new Instance($version))->connect();
        $statement = $session->analyze('DROP TABLE t, t');
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        (new \MySqlMemory\Session\Access\Preparation())->check($statement, $session);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (self::providerReleases() as [$version]) {
            foreach (['DROP TABLE pbt.t, pbt.t', 'DROP TEMPORARY TABLES IF EXISTS pbt.t, pbt.t', 'DROP TABLE pbt.t, pbt.t, later', 'CREATE PROCEDURE pbt.p() DROP TABLE t, pbt.t', 'CREATE PROCEDURE pbt.p() BEGIN IF 0 THEN DROP TABLE t, pbt.t; END IF; END', 'CREATE PROCEDURE pbt.p() BEGIN DECLARE x INT; BEGIN DECLARE y INT; DROP TABLE t, pbt.t; END; END'] as $sql) {
                yield $version . ' ' . $sql => [$version, $sql];
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
