<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Message;

use MySqlMemory\Error\Family\TransactionError;
use MySqlMemory\Error\Message\TransactionMessages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransactionMessages::class)]
#[Small]
final class TransactionMessagesTest extends TestCase
{
    public function testDefinitionsCoverEveryErrorCase(): void
    {
        $cases = array_map(static fn (TransactionError $error): string => $error->name, TransactionError::cases());
        self::assertSame($cases, array_keys(TransactionMessages::DEFINITIONS));
    }
}
