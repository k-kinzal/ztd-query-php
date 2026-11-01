<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Message;

use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Message\AccountMessages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountMessages::class)]
#[Small]
final class AccountMessagesTest extends TestCase
{
    public function testDefinitionsCoverEveryErrorCase(): void
    {
        $cases = array_map(static fn (AccountError $error): string => $error->name, AccountError::cases());
        self::assertSame($cases, array_keys(AccountMessages::DEFINITIONS));
    }
}
