<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Literal\LineText;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

#[CoversClass(LineText::class)]
#[Small]
final class LineTextTest extends TestCase
{
    public function testValidUsesTheFirstNulWithoutRestrictingOrdinaryText(): void
    {
        $values = ["a\nb", "a\nb\0c", "a\0b\nc", "a\rb", "a\tb", 'a\\nb', ''];
        self::assertSame([false, false, true, true, true, true, true], array_map(static fn (string $value): bool => (new LineText())->valid(new Text($value)), $values));
        self::assertSame("a\nb", (new Text("a\nb"))->value);
    }
}
