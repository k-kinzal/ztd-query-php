<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Known;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\TypeChangeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(TypeChangeAttribute::class)]
#[Small]
final class TypeChangeAttributeTest extends TestCase
{
    public function testReadingFollowsTheCommand(): void
    {
        self::assertSame([Reading::Storage, Reading::Function, Reading::Ignored], [TypeChangeAttribute::Storage->reading(), TypeChangeAttribute::TypmodOut->reading(), TypeChangeAttribute::Collatable->reading()]);
    }

    public function testNamedKnowsOnlyTheAmericanSpelling(): void
    {
        self::assertSame([TypeChangeAttribute::Analyze, null], [TypeChangeAttribute::named('analyze'), TypeChangeAttribute::named('analyse')]);
    }

    public function testTextIsTheAttributeName(): void
    {
        self::assertSame('typmod_in', TypeChangeAttribute::TypmodIn->text());
    }
}
