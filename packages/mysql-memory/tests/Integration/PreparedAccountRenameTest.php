<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Command\Prepared\PreparedStatement;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversNothing]
#[Medium]
final class PreparedAccountRenameTest extends TestCase
{
    public function testPrepareReportsOperandWarningsWithoutRepeatingThemAtExecute(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query("PREPARE s FROM 'RENAME USER full TO full'");
        self::assertSame([4119, 4119], array_column($session->diagnostics->conditions, 1));
        $failure = $session->run('EXECUTE s')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $failure);
        self::assertSame(1396, $failure->getCode());
        self::assertSame([1396], array_column($session->diagnostics->conditions, 1));
    }

    public function testParametersHasNoValueMarkers(): void
    {
        $request = new RenameUser([new M\Account\User\UserRenaming(new M\Name\CurrentUser(), new M\Name\CurrentUser())]);
        self::assertSame([], ParameterBindings::capture($request)->types);
    }

    public function testExecuteUsesTheOriginalDatabaseAndRestoresTheCurrentDatabase(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $statement = (new \MySqlMemory\Session\Access\Preparation())->read('RENAME USER CURRENT_USER TO CURRENT_USER', $session);
        self::assertInstanceOf(RenameUser::class, $statement);
        $definition = PreparedStatement::capture($statement, $session);
        $session->query('DROP DATABASE d');
        $this->expectExceptionCode(1396);
        try {
            $definition->execute($session, 'opaque request label', []);
        } finally {
            self::assertSame('', $session->variables->database);
            self::assertSame('opaque request label', $session->text);
        }
    }

    public function testRunPreservesTheSameInstructionAndItsDiagnostics(): void
    {
        $request = new RenameUser([new M\Account\User\UserRenaming(new M\Name\CurrentUser(), new M\Name\CurrentUser())]);
        $session = (new \MySqlMemory\Instance())->connect();
        $operation = (new \MySqlMemory\Session\Statement\Binding())->statement($session, $request);
        $definition = PreparedStatement::capture($operation, $session);
        $reply = $definition->run($session, 'opaque label', []);
        self::assertSame($operation, $definition->statement);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(1396, $reply[0]->getCode());
        self::assertSame([['Error', 1396, "Operation RENAME USER failed for 'root'@'%'"]], $session->diagnostics->conditions);
    }
}
