<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AddOrDrop;

#[CoversClass(AddOrDrop::class)]
#[Small]
final class AddOrDropTest extends TestCase
{
    public function testCasesSpellTheChoices(): void
    {
        self::assertSame(['ADD', 'DROP'], array_map(static fn (AddOrDrop $choice): string => $choice->value, AddOrDrop::cases()));
    }
}
