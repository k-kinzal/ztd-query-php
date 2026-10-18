<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Prepared;

use MySqlMemory\Command\Prepared\PendingRevocation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRevokePrivileges;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(PendingRevocation::class)]
#[Medium]
final class PendingRevocationTest extends TestCase
{
    public function testParametersHasNoValueMarkers(): void
    {
        $request = new DeferredRevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Procedure, new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
        self::assertSame([], (new PendingRevocation($request, 'd'))->parameters()->types);
    }

    public function testExecuteUsesTheOriginalDatabaseAndRestoresTheCurrentDatabase(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $definition = (new \MySqlMemory\Session\Access\Preparation())->read('REVOKE ALL ON PROCEDURE * FROM u', $session);
        self::assertInstanceOf(PendingRevocation::class, $definition);
        $session->query('DROP DATABASE d');
        $this->expectExceptionCode(1144);
        try {
            $definition->execute($session, 'opaque request label', []);
        } finally {
            self::assertSame('', $session->variables->database);
            self::assertSame('opaque request label', $session->text);
        }
    }

    public function testRunPreservesTheSameInstructionAndItsDiagnostics(): void
    {
        $request = new DeferredRevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Procedure, new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
        $definition = new PendingRevocation($request, 'd');
        $session = (new \MySqlMemory\Instance())->connect();
        $reply = $definition->run($session, 'opaque label', []);
        self::assertSame($request, $definition->request);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(1144, $reply[0]->getCode());
        self::assertSame([['Error', 1144, 'Illegal GRANT/REVOKE command; please consult the manual to see which privileges can be used']], $session->diagnostics->conditions);
    }
}
