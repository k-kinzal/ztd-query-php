<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\AggregateAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\BaseTypeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\CollationAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\ConfigurationAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\DictionaryAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\ParserAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\RangeAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\TemplateAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

#[CoversClass(KnownAttribute::class)]
#[Small]
final class KnownAttributeTest extends TestCase
{
    public function testEveryCommandSetIsAKnownAttribute(): void
    {
        self::assertContains(KnownAttribute::class, class_implements(OperatorAttribute::class));
        self::assertContains(KnownAttribute::class, class_implements(AggregateAttribute::class));
        self::assertContains(KnownAttribute::class, class_implements(BaseTypeAttribute::class));
        self::assertContains(KnownAttribute::class, class_implements(RangeAttribute::class));
        self::assertContains(KnownAttribute::class, class_implements(CollationAttribute::class));
        self::assertContains(KnownAttribute::class, class_implements(ParserAttribute::class));
        self::assertContains(KnownAttribute::class, class_implements(TemplateAttribute::class));
        self::assertContains(KnownAttribute::class, class_implements(DictionaryAttribute::class));
        self::assertContains(KnownAttribute::class, class_implements(ConfigurationAttribute::class));
    }

    public function testReadingAnswersHowTheCommandReadsTheValue(): void
    {
        self::assertSame([Reading::Function, Reading::Type], [OperatorAttribute::Function->reading(), RangeAttribute::Subtype->reading()]);
    }

    public function testNamedComparesRespectingCase(): void
    {
        self::assertSame([OperatorAttribute::Function, null], [OperatorAttribute::named('function'), OperatorAttribute::named('FUNCTION')]);
    }

    public function testTextIsTheNameTheCommandComparesWith(): void
    {
        self::assertSame('subtype_opclass', RangeAttribute::SubtypeOpclass->text());
    }
}
