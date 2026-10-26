<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class RoleCreationAnalysisBoundaryTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerVersions')]
    public function testAnalysisRetainsTheRequestWithoutChangingAccountsOrTransaction(string $version): void
    {
        $session = (new \MySqlMemory\Instance($version))->connect();
        $session->query('START TRANSACTION');
        $accounts = $session->instance->accounts->accounts;
        $counters = [$session->variables->rowCount, $session->variables->foundRows];
        $status = clone $session->instance->registry->status;
        $statement = $session->analyze("CREATE ROLE r, ''");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\CreateRole::class, $statement);
        self::assertNotNull($statement->problem);
        self::assertTrue($session->transaction->open);
        self::assertSame($accounts, $session->instance->accounts->accounts);
        self::assertSame($counters, [$session->variables->rowCount, $session->variables->foundRows]);
        self::assertEquals($status, $session->instance->registry->status);
        self::assertNull($session->instance->accounts->find(new \MySqlMemory\Account\Identity('r', '%')));
        $session->text = 'opaque role request';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(1396);
        try {
            (new \MySqlMemory\Session\Execution($session))->statement($statement);
        } finally {
            self::assertFalse($session->transaction->open);
            self::assertSame('opaque role request', $session->text);
            self::assertNull($session->instance->accounts->find(new \MySqlMemory\Account\Identity('r', '%')));
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerVersions')]
    public function testStoredRequestWaitsUntilCall(string $version): void
    {
        $session = (new \MySqlMemory\Instance($version, [], ['d']))->connect(database: 'd');
        $session->query("CREATE PROCEDURE p() CREATE ROLE r, ''");
        self::assertNull($session->instance->accounts->find(new \MySqlMemory\Account\Identity('r', '%')));
        $failure = $session->run('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $failure);
        self::assertSame(1396, $failure->getCode());
        self::assertNull($session->instance->accounts->find(new \MySqlMemory\Account\Identity('r', '%')));
        self::assertSame([], $session->transaction->statements->running);
        self::assertSame([], $session->running->using);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        foreach (\Container\MySqlRelease::versions() as $version) {
            if (!str_starts_with($version, '5.')) {
                yield $version => [$version];
            }
        }
    }

    public function testRefusedPreservesTheAnonymousAccountErrorAndDiagnostics(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7'))->connect();
        $reply = $session->run("CREATE ROLE r, ''")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply);
        self::assertSame(1396, $reply->getCode());
        self::assertSame([['Error', 1396, 'Operation CREATE ROLE failed for anonymous user']], $session->diagnostics->conditions);
    }

    public function testReadingKeepsOperandWarningsBeforeExecutionFailure(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $failure = $session->run("CREATE ROLE full@full, ''")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $failure);
        self::assertSame(1396, $failure->getCode());
        self::assertSame([4119, 4119, 1396], array_column($session->diagnostics->conditions, 1));
    }

    public function testRefusedKeepsConditionalAnonymousCreationDependentOnTheCatalog(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7'))->connect();
        $missing = $session->run("CREATE ROLE IF NOT EXISTS ''")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
        self::assertSame(1396, $missing->getCode());
        $session->query("CREATE USER ''");
        $existing = $session->run("CREATE ROLE IF NOT EXISTS ''")[0];
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $existing);
        self::assertSame([['Note', 3163, "Authorization ID ''@'%' already exists."]], $session->diagnostics->conditions);
    }

    public function testRefusedReportsTheUnsupportedPreparedCommandBeforeItsAccountCondition(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7'))->connect();
        $reply = $session->run("PREPARE st FROM \"CREATE ROLE r, ''\"")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply);
        self::assertSame(1295, $reply->getCode());
        $missing = $session->run('EXECUTE st')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
        self::assertSame(1243, $missing->getCode());
    }
}
