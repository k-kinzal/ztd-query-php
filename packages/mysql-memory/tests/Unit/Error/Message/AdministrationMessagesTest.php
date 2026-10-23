<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Message;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Message\AdministrationMessages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdministrationMessages::class)]
#[Small]
final class AdministrationMessagesTest extends TestCase
{
    public function testDefinitionsCoverEveryErrorCase(): void
    {
        $cases = array_map(static fn (AdministrationError $error): string => $error->name, AdministrationError::cases());
        self::assertSame($cases, array_keys(AdministrationMessages::DEFINITIONS));
    }
}
