<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Known;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\CollationAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(CollationAttribute::class)]
#[Small]
final class CollationAttributeTest extends TestCase
{
    public function testReadingFollowsTheCommand(): void
    {
        self::assertSame([Reading::Collation, Reading::Text, Reading::Provider, Reading::Boolean], [CollationAttribute::From->reading(), CollationAttribute::LcCtype->reading(), CollationAttribute::Provider->reading(), CollationAttribute::Deterministic->reading()]);
    }

    public function testNamedComparesRespectingCase(): void
    {
        self::assertSame([CollationAttribute::From, null], [CollationAttribute::named('from'), CollationAttribute::named('FROM')]);
    }

    public function testTextIsTheAttributeName(): void
    {
        self::assertSame('from', CollationAttribute::From->text());
    }
}
