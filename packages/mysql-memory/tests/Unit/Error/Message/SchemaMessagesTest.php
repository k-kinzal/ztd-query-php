<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Message;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Message\SchemaMessages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaMessages::class)]
#[Small]
final class SchemaMessagesTest extends TestCase
{
    public function testDefinitionsCoverEveryErrorCase(): void
    {
        $cases = array_map(static fn (SchemaError $error): string => $error->name, SchemaError::cases());
        self::assertSame($cases, array_keys(SchemaMessages::DEFINITIONS));
    }
}
