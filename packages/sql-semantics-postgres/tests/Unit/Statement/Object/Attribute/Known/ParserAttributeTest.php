<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Known;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\ParserAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(ParserAttribute::class)]
#[Small]
final class ParserAttributeTest extends TestCase
{
    public function testReadingFollowsTheCommand(): void
    {
        self::assertSame([Reading::Function, Reading::Function, Reading::Function, Reading::Function, Reading::Function], array_map(static fn (ParserAttribute $attribute): Reading => $attribute->reading(), ParserAttribute::cases()));
    }

    public function testNamedComparesRespectingCase(): void
    {
        self::assertSame([ParserAttribute::Start, null], [ParserAttribute::named('start'), ParserAttribute::named('START')]);
    }

    public function testTextIsTheAttributeName(): void
    {
        self::assertSame('start', ParserAttribute::Start->text());
    }
}
