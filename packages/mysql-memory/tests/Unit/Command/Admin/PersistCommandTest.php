<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\PersistCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PersistCommand::class)]
#[Small]
final class PersistCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new PersistCommand())->clearsDiagnostics());
    }

    public function testExecuteResetsEverySetting(): void
    {
        $session = (new Instance())->connect();
        $session->query('BEGIN');
        $reply = $session->query('RESET PERSIST')[0];

        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame([0, true], [$reply->affectedRows, $session->transaction->open]);
    }

    public function testExecuteRefusesAVariableWithoutSetting(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3615);
        $this->expectExceptionMessage('Variable _sfQbfmWOWUhwT1.AFTER does not exist in persisted config file');

        $session->query('RESET PERSIST _sfQbfmWOWUhwT1.AFTER');
    }

    public function testExecuteWarnsUnderIfExists(): void
    {
        $session = (new Instance())->connect();
        $session->query('RESET PERSIST IF EXISTS DEFAULT .EXECUTE');
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '3615', 'Variable default.EXECUTE does not exist in persisted config file']], $warnings->rows);
    }
}
