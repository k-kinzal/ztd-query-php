<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\PendingRoleGrant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredGrantRoles;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(PendingRoleGrant::class)]
#[Medium]
final class PendingRoleGrantTest extends TestCase
{
    public function testParametersHasNoValueMarkers(): void
    {
        $request = new DeferredGrantRoles([new M\Account\Privilege\Item\GrantedRole(new M\Name\AccountName(new Name('')))], [new M\Name\CurrentUser()]);
        self::assertSame([], (new PendingRoleGrant($request, 'd'))->parameters()->types);
    }

    public function testExecuteUsesTheOriginalDatabaseAndRestoresTheCurrentDatabase(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $definition = (new \MySqlMemory\Session\Access\Preparation())->read("GRANT '' TO CURRENT_USER", $session);
        self::assertInstanceOf(PendingRoleGrant::class, $definition);
        $session->query('DROP DATABASE d');
        $this->expectExceptionCode(3524);
        try {
            $definition->execute($session, 'opaque request label', []);
        } finally {
            self::assertSame('', $session->variables->database);
            self::assertSame('opaque request label', $session->text);
        }
    }

    public function testRunPreservesTheSameInstructionAndItsDiagnostics(): void
    {
        $request = new DeferredGrantRoles([new M\Account\Privilege\Item\GrantedRole(new M\Name\AccountName(new Name('')))], [new M\Name\CurrentUser()]);
        $definition = new PendingRoleGrant($request, 'd');
        $session = (new \MySqlMemory\Instance())->connect();
        $reply = $definition->run($session, 'opaque label', []);
        self::assertSame($request, $definition->request);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(3524, $reply[0]->getCode());
        self::assertSame([['Error', 3524, 'Failed to grant ``@`%`` to `root`@`%`']], $session->diagnostics->conditions);
    }
}
