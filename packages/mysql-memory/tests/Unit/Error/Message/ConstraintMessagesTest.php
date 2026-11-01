<?php

declare(strict_types=1);

namespace Tests\Unit\Error\Message;

use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Message\ConstraintMessages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConstraintMessages::class)]
#[Small]
final class ConstraintMessagesTest extends TestCase
{
    public function testDefinitionsCoverEveryErrorCase(): void
    {
        $cases = array_map(static fn (ConstraintError $error): string => $error->name, ConstraintError::cases());
        self::assertSame($cases, array_keys(ConstraintMessages::DEFINITIONS));
    }
}
