<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\ForeignServerCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer;

#[CoversClass(ForeignServerCommand::class)]
#[Small]
final class ForeignServerCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ForeignServerCommand())->clearsDiagnostics());
    }

    public function testExecuteReportsOneAffectedRow(): void
    {
        $session = (new Instance())->connect();
        $replies = $session->query("CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'h'); ALTER SERVER S OPTIONS (PORT 12); DROP SERVER s; DROP SERVER IF EXISTS s");

        self::assertSame([1, 1, 1, 1], array_map(static fn ($reply): int => $reply instanceof Completion ? $reply->affectedRows : -1, $replies));
    }

    public function testExecuteComparesNamesWithoutRegardToCaseAndAccents(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE SERVER `Admin_É` FOREIGN DATA WRAPPER mysql OPTIONS (HOST \'h\')');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1476);
        $this->expectExceptionMessage('The foreign server, admin_e, you are trying to create already exists.');

        $session->query("CREATE SERVER admin_e FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'h')");
    }

    public function testExecuteRefusesAMissingServer(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1477);
        $this->expectExceptionMessage('The foreign server name you are trying to reference does not exist. Data source error:  NO');

        $session->query("ALTER SERVER NO OPTIONS (SOCKET 'text')");
    }

    public function testExecuteNamesTheFirst64BytesOfAMissingServer(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessageMatches('/Data source error:  a{61}猫\z/u');

        $session->query('DROP SERVER `' . str_repeat('a', 61) . '猫猫`');
    }

    public function testExecuteCommitsTheOpenTransaction(): void
    {
        $session = (new Instance())->connect();
        $session->query("BEGIN; CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'h')");

        self::assertFalse($session->transaction->open);
    }

    public function testOptionsLetALaterOptionOverrideAnEarlierOne(): void
    {
        $instance = new Instance();
        $statement = $instance->connect()->analyze("CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'h', HOST 'i', PORT 3306)")->statement;
        self::assertInstanceOf(CreateServer::class, $statement);

        self::assertSame(['host' => 'i', 'port' => 3306], (new ForeignServerCommand())->options($statement->options));
        self::assertSame([], $instance->registry->servers);
        self::assertSame('S', Registry::key('s'));
    }
}
