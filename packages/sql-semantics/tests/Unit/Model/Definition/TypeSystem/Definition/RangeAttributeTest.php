<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind;
use SqlSemantics\Model\Definition\TypeSystem\Definition\RangeAttribute;

#[CoversClass(RangeAttribute::class)]
final class RangeAttributeTest extends TestCase
{
    public function testSpellingIsTheSqlName(): void
    {
        self::assertSame('MULTIRANGE_TYPE_NAME', RangeAttribute::MultirangeTypeName->spelling());
    }

    public function testKindTypesOnlyTheSubtypeAsAType(): void
    {
        self::assertSame(DefinitionKind::Type, RangeAttribute::Subtype->kind());
        self::assertSame(DefinitionKind::Name, RangeAttribute::Collation->kind());
    }

    public function testChooseHasNoKeywords(): void
    {
        self::assertNull(RangeAttribute::Canonical->choose('x'));
    }
}
