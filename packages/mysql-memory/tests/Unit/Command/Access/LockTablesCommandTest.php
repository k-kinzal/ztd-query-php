<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Access;

use MySqlMemory\Command\Access\LockTablesCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(LockTablesCommand::class)]
#[Small]
final class LockTablesCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new LockTablesCommand())->clearsDiagnostics());
    }

    public function testExecuteLocksAndUnlocksTheTables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d; CREATE TABLE t (a INT); CREATE TABLE u (a INT)');

        $session->query('LOCK TABLES t READ, u AS x WRITE');
        $locks = $session->locks;
        $session->query('UNLOCK TABLES');

        self::assertSame([['d', 't', 't', false], ['d', 'u', 'x', true]], $locks);
        self::assertSame([], $session->locks);
    }

    public function testExecuteRefusesAnUnknownDatabase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1049);
        $this->expectExceptionMessage("Unknown database 'abc'");

        $session->query('LOCK TABLES abc.q READ');
    }

    public function testExecuteRefusesATableThatDoesNotExist(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; USE d');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);

        $session->query('LOCK TABLES nope READ');
    }

    public function testExecuteReportsATableOfAMissingDatabaseAsMissingIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1146);

        $session->query('LOCK TABLES nodb.t READ');
    }
}
