<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AccountOption::class)]
#[Medium]
final class AccountOptionTest extends TestCase
{
    public function testRenderWritesTheNumberAndItsUnit(): void
    {
        self::assertSame('CREATE USER u PASSWORD EXPIRE INTERVAL 90 DAY PASSWORD REUSE INTERVAL DEFAULT PASSWORD_LOCK_TIME UNBOUNDED FAILED_LOGIN_ATTEMPTS 3', (new Inspection())->inspect('create user u password expire interval 90 day password reuse interval default password_lock_time unbounded failed_login_attempts 3')->toString());
    }
}
