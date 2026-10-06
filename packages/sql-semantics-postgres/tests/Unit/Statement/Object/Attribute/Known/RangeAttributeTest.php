<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Known;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\RangeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(RangeAttribute::class)]
#[Small]
final class RangeAttributeTest extends TestCase
{
    public function testReadingFollowsTheCommand(): void
    {
        self::assertSame([Reading::Type, Reading::OperatorClass, Reading::Collation, Reading::Function, Reading::CreatedType], [RangeAttribute::Subtype->reading(), RangeAttribute::SubtypeOpclass->reading(), RangeAttribute::Collation->reading(), RangeAttribute::Canonical->reading(), RangeAttribute::MultirangeTypeName->reading()]);
    }

    public function testNamedComparesRespectingCase(): void
    {
        self::assertSame([RangeAttribute::Subtype, null], [RangeAttribute::named('subtype'), RangeAttribute::named('SUBTYPE')]);
    }

    public function testTextIsTheAttributeName(): void
    {
        self::assertSame('subtype', RangeAttribute::Subtype->text());
    }
}
