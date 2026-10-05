<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Known;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\BaseTypeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(BaseTypeAttribute::class)]
#[Small]
final class BaseTypeAttributeTest extends TestCase
{
    public function testReadingFollowsTheCommand(): void
    {
        self::assertSame([Reading::Function, Reading::Type, Reading::Length, Reading::Text, Reading::Alignment, Reading::Storage, Reading::Boolean], [BaseTypeAttribute::Analyse->reading(), BaseTypeAttribute::Like->reading(), BaseTypeAttribute::Internallength->reading(), BaseTypeAttribute::Category->reading(), BaseTypeAttribute::Alignment->reading(), BaseTypeAttribute::Storage->reading(), BaseTypeAttribute::Collatable->reading()]);
    }

    public function testNamedComparesRespectingCase(): void
    {
        self::assertSame([BaseTypeAttribute::Like, null], [BaseTypeAttribute::named('like'), BaseTypeAttribute::named('LIKE')]);
    }

    public function testTextIsTheAttributeName(): void
    {
        self::assertSame('like', BaseTypeAttribute::Like->text());
    }
}
