<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Session\Parse\Definition\RoleGrantInput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoleGrantInput::class)]
#[Medium]
final class RoleGrantInputTest extends TestCase
{
    public function testRefusedPreservesRecipientLookupBeforeTheAnonymousRoleCondition(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $missing = $session->run("GRANT '' TO u")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
        self::assertSame(3523, $missing->getCode());
        $session->query('CREATE USER u');
        $known = $session->run("GRANT '' TO u")[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $known);
        self::assertSame(3524, $known->getCode());
    }

    public function testRefusedRetainsPreparedInvocationBeforeTheAccountsExist(): void
    {
        $session = (new \MySqlMemory\Instance())->connect();
        $session->query("PREPARE s FROM 'GRANT r TO r'");
        $missing = $session->run('EXECUTE s')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $missing);
        self::assertSame(3523, $missing->getCode());
        $session->query('CREATE USER r');
        $known = $session->run('EXECUTE s')[0];
        self::assertInstanceOf(\MySqlMemory\Error\SqlError::class, $known);
        self::assertSame(4027, $known->getCode());
    }
}
