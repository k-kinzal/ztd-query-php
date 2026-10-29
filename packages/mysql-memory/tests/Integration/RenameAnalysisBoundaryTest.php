<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Account\Identity;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;

#[CoversNothing]
#[Medium]
final class RenameAnalysisBoundaryTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testAnalysisCannotRenameAccountsOrCommitAnOpenTransaction(string $version, string $remaining): void
    {
        $session = (new \MySqlMemory\Instance($version, [], ['d']))->connect(database: 'd');
        $session->query('CREATE USER a; CREATE TABLE t(a INT); START TRANSACTION; INSERT INTO t VALUES(1)');

        $statement = $session->analyze('RENAME USER a TO b, a TO a');

        self::assertInstanceOf(RenameUser::class, $statement);
        self::assertNotNull($statement->problem);
        self::assertTrue($session->transaction->open);
        self::assertNotNull($session->instance->accounts->find(new Identity('a', '%')));
        self::assertNull($session->instance->accounts->find(new Identity('b', '%')));
        $session->text = 'opaque request label';
        try {
            $this->expectException(\MySqlMemory\Error\SqlError::class);
            $this->expectExceptionCode(1396);

            (new Execution($session))->statement($statement);
        } finally {
            self::assertSame('opaque request label', $session->text);
            self::assertFalse($session->transaction->open);
            self::assertNotNull($session->instance->accounts->find(new Identity($remaining, '%')));
            $session->query('ROLLBACK');
            $rows = $session->query('SELECT a FROM t')[0];
            self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $rows);
            self::assertSame([['1']], $rows->rows);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerVersions(): iterable
    {
        foreach (\Container\MySqlRelease::versions() as $version) {
            yield $version => [$version, str_starts_with($version, '5.') ? 'b' : 'a'];
        }
    }

    public function testRefusedPreservesAccountFailureAcrossCreation(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $missing = $session->run('RENAME USER u TO u')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
        self::assertSame(1396, $missing->getCode());
        $session->query('CREATE USER u');
        $known = $session->run('RENAME USER u TO u')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $known);
        self::assertSame(1396, $known->getCode());
    }

    public function testRefusedRetainsPreparedInvocationBeforeTheAccountsExist(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query("PREPARE s FROM 'RENAME USER r TO r'");
        $missing = $session->run('EXECUTE s')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
        self::assertSame(1396, $missing->getCode());
        $session->query('CREATE USER r');
        $known = $session->run('EXECUTE s')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $known);
        self::assertSame(1396, $known->getCode());
    }

    #[DataProvider('providerVersions')]
    public function testStoredRenameWaitsUntilCallAndUsesTheCommonExecutionBoundary(string $version, string $remaining): void
    {
        $session = (new \MySqlMemory\Instance($version, [], ['d']))->connect(database: 'd');
        $session->query('CREATE USER a; CREATE PROCEDURE p() RENAME USER a TO b, a TO a');
        self::assertNotNull($session->instance->accounts->find(new Identity('a', '%')));
        self::assertNull($session->instance->accounts->find(new Identity('b', '%')));
        $failure = $session->run('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $failure);
        self::assertSame(1396, $failure->getCode());
        self::assertNotNull($session->instance->accounts->find(new Identity($remaining, '%')));
        self::assertSame([], $session->transaction->statements->running);
        self::assertSame([], $session->running->using);
    }

    public function testReadingKeepsOperandWarningsBeforeExecutionFailure(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $failure = $session->run('RENAME USER full@full TO full')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $failure);
        self::assertSame(1396, $failure->getCode());
        self::assertSame([4119, 4119, 1396], array_column($session->diagnostics->conditions, 1));
    }
}
