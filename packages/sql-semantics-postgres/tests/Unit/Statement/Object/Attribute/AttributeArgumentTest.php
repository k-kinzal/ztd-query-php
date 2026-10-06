<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\AttributeArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\BooleanArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\ChoiceArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\IntegerArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\LengthArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\NameArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\TextArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\TypeArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;

#[CoversClass(AttributeArgument::class)]
#[Small]
final class AttributeArgumentTest extends TestCase
{
    public function testEveryReadValueIsAnAttributeArgument(): void
    {
        self::assertContains(AttributeArgument::class, class_implements(NameArgument::class));
        self::assertContains(AttributeArgument::class, class_implements(TypeArgument::class));
        self::assertContains(AttributeArgument::class, class_implements(BooleanArgument::class));
        self::assertContains(AttributeArgument::class, class_implements(IntegerArgument::class));
        self::assertContains(AttributeArgument::class, class_implements(TextArgument::class));
        self::assertContains(AttributeArgument::class, class_implements(LengthArgument::class));
        self::assertContains(AttributeArgument::class, class_implements(ChoiceArgument::class));
    }

    public function testFitsTellsTheReadingOfEachArgument(): void
    {
        self::assertSame([true, false], [(new TextArgument(new StringConstant('x')))->fits(Reading::Text), (new BooleanArgument())->fits(Reading::Text)]);
    }

    public function testAReadValueIsNotAWrittenOptionValue(): void
    {
        self::assertNotContains(OptionArgument::class, class_implements(NameArgument::class));
    }
}
