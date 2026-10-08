<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\InstanceCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstanceCommand::class)]
#[Small]
final class InstanceCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new InstanceCommand())->clearsDiagnostics());
    }

    public function testExecuteReloadsTlsAndTakesTheBackupLock(): void
    {
        $replies = (new Instance())->connect()->query('ALTER INSTANCE RELOAD TLS FOR CHANNEL MYSQL_MAIN; ALTER INSTANCE ENABLE INNODB REDO_LOG; LOCK INSTANCE FOR BACKUP; UNLOCK INSTANCE');

        self::assertSame([0, 0, 0, 0], array_map(static fn ($reply): int => $reply instanceof Completion ? $reply->affectedRows : -1, $replies));
    }

    public function testExecuteRefusesAnotherTlsChannel(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1149);

        $session->query('ALTER INSTANCE RELOAD TLS FOR CHANNEL PROXY NO ROLLBACK ON ERROR');
    }

    public function testExecuteFindsNoBinaryLogEncryption(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3805);
        $this->expectExceptionMessage("Cannot rotate binary log master key when 'binlog-encryption' is off.");

        $session->query('ALTER INSTANCE ROTATE BINLOG MASTER KEY');
    }

    public function testExecuteFindsNoKeyring(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(4035);
        $this->expectExceptionMessage('Keyring reload failed. Please check error log for more details.');

        $session->query('ALTER INSTANCE RELOAD KEYRING');
    }

    public function testExecuteCommitsTheOpenTransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN; ALTER INSTANCE RELOAD TLS');

        self::assertFalse($session->transaction->open);
    }
}
