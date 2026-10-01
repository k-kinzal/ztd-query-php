<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Schema\Definition\KeyDirection;

#[CoversClass(KeyDirection::class)]
#[Small]
final class KeyDirectionTest extends TestCase
{
    public function testCasesDistinguishTheDeclaredChoices(): void
    {
        self::assertSame(['', 'ASC', 'DESC'], array_map(static fn (KeyDirection $choice): string => $choice->value, KeyDirection::cases()));
    }
}
