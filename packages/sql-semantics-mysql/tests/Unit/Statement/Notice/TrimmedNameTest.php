<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Notice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Notice\TrimmedName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TrimmedName::class)]
#[Small]
final class TrimmedNameTest extends TestCase
{
    public function testCodeReturnsTheNameNormalizationWarning(): void
    {
        self::assertSame(1466, (new TrimmedName(new Name(' name')))->code());
    }

    public function testMessageStopsAtNulWithoutDiscardingTheOperand(): void
    {
        $name = new Name(" name\0tail");
        $warning = new TrimmedName($name);

        self::assertSame("Leading spaces are removed from name ' name'", $warning->message());
        self::assertSame($name, $warning->name);
    }

    public function testMessageBoundsTheWholeMessageAtUtf8CharacterBoundaries(): void
    {
        $warning = new TrimmedName(new Name('Database (' . str_repeat('é', 300) . ')'));

        self::assertSame(510, strlen($warning->message()));
        self::assertTrue(mb_check_encoding($warning->message(), 'UTF-8'));
        self::assertSame(611, strlen($warning->name->value));
    }
}
