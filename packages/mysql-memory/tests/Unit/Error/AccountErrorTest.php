<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\AccountError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountError::class)]
#[Small]
final class AccountErrorTest extends TestCase
{
    public function testNumberAnswersTheBackingValue(): void
    {
        self::assertSame(1396, AccountError::CannotUser->number());
    }

    public function testTryFromFindsTheCaseOfANumberOfTheFamily(): void
    {
        self::assertSame(AccountError::CannotUser, AccountError::tryFrom(1396));
    }

    public function testSqlStateAnswersTheStateOfTheError(): void
    {
        self::assertSame('HY000', AccountError::CannotUser->sqlState());
    }

    public function testMessageFillsTheArgumentsIntoTheFormat(): void
    {
        self::assertSame("Operation DROP USER failed for 'u'@'%'", AccountError::CannotUser->message('DROP USER', "'u'@'%'"));
    }

    public function testErrorAnswersTheNumberStateAndMessage(): void
    {
        $error = AccountError::CannotUser->error('DROP USER', "'u'@'%'");

        self::assertSame([1396, 'HY000', "Operation DROP USER failed for 'u'@'%'", AccountError::CannotUser], [$error->getCode(), $error->sqlState(), $error->getMessage(), $error->error]);
    }
}
