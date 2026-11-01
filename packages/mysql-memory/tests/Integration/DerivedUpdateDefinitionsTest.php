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
final class DerivedUpdateDefinitionsTest extends TestCase
{
    #[DataProvider('providerMissingDatabases')]
    public function testUnknownAssignmentVariablePrecedesTheLegacyTargetCondition(string $version): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: 'pbt');
        $statement = $session->analyze('CREATE PROCEDURE p() UPDATE (SELECT 1 AS c) AS d SET c=@@missing_variable');
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1193);
        (new Execution($session))->statement($statement);
    }

    #[DataProvider('providerMissingDatabases')]
    public function testUnqualifiedAssignmentFunctionPrecedesTheLegacyTargetCondition(string $version): void
    {
        $session = (new Instance($version))->connect();
        $statement = $session->analyze('UPDATE (SELECT 1 AS c) AS d SET c=missing_function()');
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode($version === '5.6.51' ? 1305 : 1046);
        (new Execution($session))->statement($statement);
    }

    #[DataProvider('providerMissingDatabases')]
    public function testDerivedInputRequiresItsDatabaseBeforeRefusingTheTarget(string $version): void
    {
        $session = (new Instance($version))->connect();
        $statement = $session->analyze('UPDATE (SELECT c FROM t) AS d SET c=1');
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        (new Execution($session))->statement($statement);
    }

    #[DataProvider('providerMissingDatabases')]
    public function testPreparationRequiresTheDerivedInputsDatabaseBeforeRefusingTheTarget(string $version): void
    {
        $session = (new Instance($version))->connect();
        $statement = $session->analyze('UPDATE (SELECT c FROM t) AS d SET c=1');
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        (new \MySqlMemory\Session\Access\Preparation())->check($statement, $session);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerMissingDatabases(): iterable
    {
        yield '5.6' => ['5.6.51'];
        yield '5.7' => ['5.7.44'];
    }

    #[DataProvider('providerLegacyBodies')]
    public function testLegacyDefinitionRefusesAnUnwritableTargetWithoutReadingSql(string $version, string $body): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: 'pbt');
        $statement = $session->analyze('CREATE PROCEDURE p() ' . $body);
        $session->text = '';
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1288);
        $this->expectExceptionMessage('The target table d of the UPDATE is not updatable');
        (new Execution($session))->statement($statement);
    }

    #[DataProvider('providerModernReleases')]
    public function testModernDefinitionKeepsASkippedDerivedUpdateForInvocation(string $version): void
    {
        $session = (new Instance($version, databases: ['pbt']))->connect(database: 'pbt');
        $statement = $session->analyze('CREATE PROCEDURE p() BEGIN IF 0 THEN UPDATE (SELECT *) AS d SET c=1; END IF; END');
        $session->text = '';
        $reply = (new Execution($session))->statement($statement);
        self::assertSame(0, $reply->warnings());
        self::assertCount(1, $session->query('CALL p()'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerLegacyBodies(): iterable
    {
        foreach (['5.6.51', '5.7.44'] as $version) {
            foreach (['SELECT *', 'SELECT 1 AS c', 'SELECT c FROM t'] as $query) {
                $update = 'UPDATE (' . $query . ') AS d SET c=1';
                yield $version . ' direct ' . $query => [$version, $update];
                yield $version . ' skipped ' . $query => [$version, 'BEGIN IF 0 THEN ' . $update . '; END IF; END'];
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerModernReleases(): iterable
    {
        foreach (['8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version => [$version];
        }
    }
}
