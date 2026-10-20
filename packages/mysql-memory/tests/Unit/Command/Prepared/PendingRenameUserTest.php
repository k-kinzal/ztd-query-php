<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\PendingRenameUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRenameUser;

#[CoversClass(PendingRenameUser::class)]
#[Medium]
final class PendingRenameUserTest extends TestCase
{
    public function testParametersHasNoValueMarkers(): void
    {
        $request = new DeferredRenameUser([new M\Account\User\UserRenaming(new M\Name\CurrentUser(), new M\Name\CurrentUser())]);
        self::assertSame([], (new PendingRenameUser($request, 'd'))->parameters()->types);
    }

    public function testExecuteUsesTheOriginalDatabaseAndRestoresTheCurrentDatabase(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $definition = (new \MySqlMemory\Session\Access\Preparation())->read('RENAME USER CURRENT_USER TO CURRENT_USER', $session);
        self::assertInstanceOf(PendingRenameUser::class, $definition);
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
        $request = new DeferredRenameUser([new M\Account\User\UserRenaming(new M\Name\CurrentUser(), new M\Name\CurrentUser())]);
        $definition = new PendingRenameUser($request, 'd');
        $session = (new \MySqlMemory\Instance())->connect();
        $reply = $definition->run($session, 'opaque label', []);
        self::assertSame($request, $definition->request);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(1396, $reply[0]->getCode());
        self::assertSame([['Error', 1396, "Operation RENAME USER failed for 'root'@'%'"]], $session->diagnostics->conditions);
    }
}
