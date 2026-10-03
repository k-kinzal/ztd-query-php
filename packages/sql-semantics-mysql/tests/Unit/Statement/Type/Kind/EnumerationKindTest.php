<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;

#[CoversClass(EnumerationKind::class)]
#[Small]
final class EnumerationKindTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEnumAndSet(): void
    {
        self::assertSame(['Enum', 'Set'], array_column(EnumerationKind::cases(), 'name'));
        self::assertSame(['ENUM', 'SET'], array_column(EnumerationKind::cases(), 'value'));
    }
}
