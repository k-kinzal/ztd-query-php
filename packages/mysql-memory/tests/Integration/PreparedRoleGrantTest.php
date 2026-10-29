<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Command\Prepared\ParameterBindings;
use MySqlMemory\Command\Prepared\PreparedStatement;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversNothing]
#[Medium]
final class PreparedRoleGrantTest extends TestCase
{
    public function testBinaryPreparationDefersRoleLookupUntilExecution(): void
    {
        $payload = '';
        $client = new \MySqlMemory\Server\Client(new \MySqlMemory\Instance(), 1, static function (string $bytes) use (&$payload): void {
            $payload .= substr($bytes, 4);
        });
        $client->handle("\x00\x82\x08\x00\x00\x00\x00\x01\xFF" . str_repeat("\x00", 23) . "root\x00\x00");
        $offset = strlen($payload);
        $client->statements->prepare('CREATE ROLE r');
        self::assertSame("\xFF" . pack('v', 1295), substr($payload, $offset, 3));
        self::assertNull($client->session()->instance->accounts->find(new \MySqlMemory\Account\Identity('r', '%')));
        $offset = strlen($payload);
        $client->statements->prepare('GRANT r TO u WITH ADMIN OPTION');
        self::assertSame("\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00", substr($payload, $offset));
        $offset = strlen($payload);
        $client->statements->handle(0x17, new \MySqlMemory\Protocol\PayloadReader("\x01\x00\x00\x00\x00\x01\x00\x00\x00"));
        self::assertSame("\xFF" . pack('v', 3523), substr($payload, $offset, 3));
        $client->session()->query('CREATE ROLE r, u');
        $offset = strlen($payload);
        $client->statements->handle(0x17, new \MySqlMemory\Protocol\PayloadReader("\x01\x00\x00\x00\x00\x01\x00\x00\x00"));
        self::assertSame("\x00", substr($payload, $offset, 1));
        $edges = $client->session()->instance->accounts->roles(new \MySqlMemory\Account\Identity('u', '%'));
        self::assertSame(["r\0%"], array_keys($edges));
        self::assertTrue($edges["r\0%"][1]);
    }

    public function testPreparationEmitsOperandNoticesOnceBeforeAccountLookup(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query("PREPARE s FROM 'GRANT full@full TO full'");
        self::assertSame([4119, 4119, 4119], array_column($session->diagnostics->conditions, 1));
        $failure = $session->run('EXECUTE s')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $failure);
        self::assertSame(3523, $failure->getCode());
        self::assertSame([3523], array_column($session->diagnostics->conditions, 1));
    }

    public function testParametersHasNoValueMarkers(): void
    {
        $request = new GrantRoles([new M\Account\Privilege\Item\GrantedRole(new M\Name\AccountName(new Name('')))], [new M\Name\CurrentUser()]);
        self::assertSame([], ParameterBindings::capture($request)->types);
    }

    public function testExecuteUsesTheOriginalDatabaseAndRestoresTheCurrentDatabase(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7', [], ['d']))->connect(database: 'd');
        $statement = (new \MySqlMemory\Session\Access\Preparation())->read("GRANT '' TO CURRENT_USER", $session);
        self::assertInstanceOf(GrantRoles::class, $statement);
        $definition = PreparedStatement::capture($statement, $session);
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
        $request = new GrantRoles([new M\Account\Privilege\Item\GrantedRole(new M\Name\AccountName(new Name('')))], [new M\Name\CurrentUser()]);
        $session = (new \MySqlMemory\Instance())->connect();
        $operation = (new \MySqlMemory\Session\Statement\Binding())->statement($session, $request);
        $definition = PreparedStatement::capture($operation, $session);
        $reply = $definition->run($session, 'opaque label', []);
        self::assertSame($operation, $definition->statement);
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply[0]);
        self::assertSame(3524, $reply[0]->getCode());
        self::assertSame([['Error', 3524, 'Failed to grant ``@`%`` to `root`@`%`']], $session->diagnostics->conditions);
    }
}
