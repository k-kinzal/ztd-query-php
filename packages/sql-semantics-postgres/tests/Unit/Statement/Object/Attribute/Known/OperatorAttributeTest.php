<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute\Known;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(OperatorAttribute::class)]
#[Small]
final class OperatorAttributeTest extends TestCase
{
    public function testReadingFollowsTheCommand(): void
    {
        self::assertSame([Reading::Type, Reading::Function, Reading::Operator, Reading::Boolean, Reading::Ignored], [OperatorAttribute::Leftarg->reading(), OperatorAttribute::Procedure->reading(), OperatorAttribute::Negator->reading(), OperatorAttribute::Hashes->reading(), OperatorAttribute::Sort1->reading()]);
    }

    public function testNamedComparesRespectingCase(): void
    {
        self::assertSame([OperatorAttribute::Leftarg, null], [OperatorAttribute::named('leftarg'), OperatorAttribute::named('LEFTARG')]);
    }

    public function testTextIsTheAttributeName(): void
    {
        self::assertSame('leftarg', OperatorAttribute::Leftarg->text());
    }
}
