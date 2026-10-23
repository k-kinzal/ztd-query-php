<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Message;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Message\DataMessages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataMessages::class)]
#[Small]
final class DataMessagesTest extends TestCase
{
    public function testDefinitionsCoverEveryErrorCase(): void
    {
        $cases = array_map(static fn (DataError $error): string => $error->name, DataError::cases());
        self::assertSame($cases, array_keys(DataMessages::DEFINITIONS));
    }
}
