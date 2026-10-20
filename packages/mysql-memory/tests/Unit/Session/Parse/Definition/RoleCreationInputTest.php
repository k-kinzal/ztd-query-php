<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Session\Parse\Definition\RoleCreationInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoleCreationInput::class)]
#[Medium]
final class RoleCreationInputTest extends TestCase
{
    public function testRefusedPreservesTheAnonymousAccountErrorAndDiagnostics(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7'))->connect();
        $reply = $session->run("CREATE ROLE r, ''")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply);
        self::assertSame(1396, $reply->getCode());
        self::assertSame([['Error', 1396, 'Operation CREATE ROLE failed for anonymous user']], $session->diagnostics->conditions);
    }

    public function testRefusedKeepsConditionalAnonymousCreationDependentOnTheCatalog(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7'))->connect();
        $missing = $session->run("CREATE ROLE IF NOT EXISTS ''")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
        self::assertSame(1396, $missing->getCode());
        $session->query("CREATE USER ''");
        $existing = $session->run("CREATE ROLE IF NOT EXISTS ''")[0];
        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $existing);
        self::assertSame([['Note', 3163, "Authorization ID ''@'%' already exists."]], $session->diagnostics->conditions);
    }

    public function testRefusedReportsTheUnsupportedPreparedCommandBeforeItsAccountCondition(): void
    {
        $session = (new \MySqlMemory\Instance('8.4.7'))->connect();
        $reply = $session->run("PREPARE st FROM \"CREATE ROLE r, ''\"")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $reply);
        self::assertSame(1295, $reply->getCode());
        $missing = $session->run('EXECUTE st')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
        self::assertSame(1243, $missing->getCode());
    }
}
