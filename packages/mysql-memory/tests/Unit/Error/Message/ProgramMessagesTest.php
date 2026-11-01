<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Message;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Message\ProgramMessages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProgramMessages::class)]
#[Small]
final class ProgramMessagesTest extends TestCase
{
    public function testDefinitionsCoverEveryErrorCase(): void
    {
        $cases = array_map(static fn (ProgramError $error): string => $error->name, ProgramError::cases());
        self::assertSame($cases, array_keys(ProgramMessages::DEFINITIONS));
    }
}
