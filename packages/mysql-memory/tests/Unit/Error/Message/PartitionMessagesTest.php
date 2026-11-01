<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Message;

use MySqlMemory\Error\Family\PartitionError;
use MySqlMemory\Error\Message\PartitionMessages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PartitionMessages::class)]
#[Small]
final class PartitionMessagesTest extends TestCase
{
    public function testDefinitionsCoverEveryErrorCase(): void
    {
        $cases = array_map(static fn (PartitionError $error): string => $error->name, PartitionError::cases());
        self::assertSame($cases, array_keys(PartitionMessages::DEFINITIONS));
    }
}
