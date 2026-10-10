<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\PasswordAccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordAccess::class)]
#[Small]
final class PasswordAccessTest extends TestCase
{
    public function testCheckRefusesOrdinaryStatementsAfterExpiration(): void
    {
        $session = (new Instance())->connect();
        $session->query('ALTER USER CURRENT_USER PASSWORD EXPIRE');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1820);
        $session->query('SELECT 1');
    }

    public function testCheckPermitsTheSessionToResetItsOwnPassword(): void
    {
        $session = (new Instance())->connect();
        $session->query('ALTER USER CURRENT_USER PASSWORD EXPIRE');
        $session->query("SET PASSWORD = 'test-only-password'");

        self::assertFalse($session->passwordExpired);
        self::assertCount(1, $session->query('SELECT 1'));
    }

    public function testChangedLeavesOtherConnectedSessionsIndependent(): void
    {
        $instance = new Instance();
        $first = $instance->connect();
        $second = $instance->connect();
        $first->query('ALTER USER CURRENT_USER PASSWORD EXPIRE');
        self::assertTrue($first->passwordExpired);
        self::assertFalse($second->passwordExpired);
        $second->query("ALTER USER CURRENT_USER IDENTIFIED BY 'test-only-password'");
        self::assertTrue($first->passwordExpired);
        self::assertFalse($second->passwordExpired);
    }

    public function testCheckKeepsParseTimeErrorsAheadOfTheRestriction(): void
    {
        $session = (new Instance())->connect();
        $session->query('ALTER USER CURRENT_USER PASSWORD EXPIRE');

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1193);
        $session->query('SELECT @@unknown_variable');
    }
}
