<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Message;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\Message\StatementMessages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementMessages::class)]
#[Small]
final class StatementMessagesTest extends TestCase
{
    public function testDefinitionsCoverEveryErrorCase(): void
    {
        $cases = array_map(static fn (StatementError $error): string => $error->name, StatementError::cases());
        self::assertSame($cases, array_keys(StatementMessages::DEFINITIONS));
    }
}
