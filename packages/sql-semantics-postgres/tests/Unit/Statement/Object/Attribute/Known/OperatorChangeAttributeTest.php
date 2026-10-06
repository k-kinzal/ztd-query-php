<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Known;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorChangeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(OperatorChangeAttribute::class)]
#[Small]
final class OperatorChangeAttributeTest extends TestCase
{
    public function testReadingFollowsTheCommand(): void
    {
        self::assertSame([Reading::Function, Reading::Operator, Reading::Boolean, Reading::Ignored], [OperatorChangeAttribute::Restrict->reading(), OperatorChangeAttribute::Commutator->reading(), OperatorChangeAttribute::Merges->reading(), OperatorChangeAttribute::Leftarg->reading()]);
    }

    public function testNamedKnowsNoObsoleteAttribute(): void
    {
        self::assertSame([OperatorChangeAttribute::Join, null, null], [OperatorChangeAttribute::named('join'), OperatorChangeAttribute::named('sort1'), OperatorChangeAttribute::named('JOIN')]);
    }

    public function testTextIsTheAttributeName(): void
    {
        self::assertSame('procedure', OperatorChangeAttribute::Procedure->text());
    }
}
