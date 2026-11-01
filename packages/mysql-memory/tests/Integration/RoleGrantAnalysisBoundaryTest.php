<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class RoleGrantAnalysisBoundaryTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('providerVersions')]
    public function testAnalysisRetainsTheRequestWithoutChangingAccountsOrTransaction(string $version): void
    {
        $session = (new \MySqlMemory\Instance($version))->connect();
        $session->query('CREATE ROLE r; START TRANSACTION');
        $accounts = $session->instance->accounts->accounts;
        $counters = [$session->variables->rowCount, $session->variables->foundRows];
        $status = clone $session->instance->registry->status;
        $statement = $session->analyze('GRANT r TO r');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles::class, $statement);
        self::assertNotNull($statement->problem);
        self::assertTrue($session->transaction->open);
        self::assertSame($accounts, $session->instance->accounts->accounts);
        self::assertSame($counters, [$session->variables->rowCount, $session->variables->foundRows]);
        self::assertEquals($status, $session->instance->registry->status);
        self::assertSame([], $session->instance->accounts->roles(new \MySqlMemory\Account\Identity('r', '%')));
        $session->text = 'opaque role request';
        $this->expectException(\MySqlMemory\Error\SqlError::class);
        $this->expectExceptionCode(4027);
        try {
            (new \MySqlMemory\Session\Execution($session))->statement($statement);
        } finally {
            self::assertFalse($session->transaction->open);
            self::assertSame('opaque role request', $session->text);
            self::assertSame([], $session->instance->accounts->roles(new \MySqlMemory\Account\Identity('r', '%')));
            self::assertSame([], $session->transaction->statements->running);
            self::assertSame([], $session->running->using);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerVersions')]
    public function testStoredRequestWaitsUntilCall(string $version): void
    {
        $session = (new \MySqlMemory\Instance($version, [], ['d']))->connect(database: 'd');
        $session->query('CREATE ROLE r; CREATE PROCEDURE p() GRANT r TO r');
        self::assertSame([], $session->instance->accounts->roles(new \MySqlMemory\Account\Identity('r', '%')));
        $failure = $session->run('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $failure);
        self::assertSame(4027, $failure->getCode());
        self::assertSame([], $session->instance->accounts->roles(new \MySqlMemory\Account\Identity('r', '%')));
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

    public function testRefusedPreservesRecipientLookupBeforeTheAnonymousRoleCondition(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $missing = $session->run("GRANT '' TO u")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
        self::assertSame(3523, $missing->getCode());
        $session->query('CREATE USER u');
        $known = $session->run("GRANT '' TO u")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $known);
        self::assertSame(3524, $known->getCode());
    }

    public function testRefusedRetainsPreparedInvocationBeforeTheAccountsExist(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query("PREPARE s FROM 'GRANT r TO r'");
        $missing = $session->run('EXECUTE s')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
        self::assertSame(3523, $missing->getCode());
        $session->query('CREATE USER r');
        $known = $session->run('EXECUTE s')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $known);
        self::assertSame(4027, $known->getCode());
    }
}
