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
final class TableCreationValidationOrderTest extends TestCase
{
    #[DataProvider('providerExisting')]
    public function testHeldDefinitionUsesTheReleaseOrderWithoutSourceText(string $version, string $suffix, int $code): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT)');
        $statement = $session->analyze('CREATE TABLE t(a INT NULL PRIMARY KEY)' . $suffix);
        $session->text = '';

        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        (new Execution($session))->statement($statement);
    }

    #[DataProvider('providerExisting')]
    public function testPreparedDefinitionDelaysItsConditionUntilExecution(string $version, string $suffix, int $code): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT)');
        $session->query("PREPARE s FROM 'CREATE TABLE t(a INT NULL PRIMARY KEY)" . $suffix . "'");

        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $session->query('EXECUTE s');
    }

    #[DataProvider('providerExisting')]
    public function testStoredDefinitionDelaysItsConditionUntilInvocation(string $version, string $suffix, int $code): void
    {
        $session = (new Instance($version, databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(id INT)');
        $session->query('CREATE PROCEDURE p() CREATE TABLE t(a INT NULL PRIMARY KEY)' . $suffix);

        $this->expectException(SqlError::class);
        $this->expectExceptionCode($code);
        $session->query('CALL p()');
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerExisting(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            yield $version => [$version, '', $version === '5.7.44' ? 1171 : 1050];
            yield $version . ' query' => [$version, ' AS SELECT 1 AS b', 1050];
        }
    }
}
