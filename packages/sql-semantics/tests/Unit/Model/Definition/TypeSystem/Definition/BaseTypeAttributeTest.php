<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Definition\BaseTypeAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind;

#[CoversClass(BaseTypeAttribute::class)]
final class BaseTypeAttributeTest extends TestCase
{
    public function testSpellingIsTheSqlName(): void
    {
        self::assertSame('TYPMOD_IN', BaseTypeAttribute::TypmodIn->spelling());
    }

    public function testKindTypesEachAttribute(): void
    {
        self::assertSame(DefinitionKind::Name, BaseTypeAttribute::Input->kind());
        self::assertSame(DefinitionKind::Type, BaseTypeAttribute::Element->kind());
        self::assertSame(DefinitionKind::Length, BaseTypeAttribute::InternalLength->kind());
        self::assertSame(DefinitionKind::Text, BaseTypeAttribute::Default->kind());
        self::assertSame(DefinitionKind::Boolean, BaseTypeAttribute::Collatable->kind());
        self::assertSame(DefinitionKind::Choice, BaseTypeAttribute::Storage->kind());
    }

    public function testChooseMatchesKeywordsCaseInsensitively(): void
    {
        self::assertSame('extended', BaseTypeAttribute::Storage->choose('Extended'));
        self::assertNull(BaseTypeAttribute::Storage->choose('compressed'));
        self::assertSame('char', BaseTypeAttribute::Alignment->choose('pg_catalog.bpchar'));
        self::assertSame('int4', BaseTypeAttribute::Alignment->choose('INT4'));
        self::assertNull(BaseTypeAttribute::Alignment->choose('int8'));
        self::assertNull(BaseTypeAttribute::Input->choose('plain'));
    }

    public function testAlterableIsLimitedToSupportFunctionsAndStorage(): void
    {
        self::assertTrue(BaseTypeAttribute::Subscript->alterable());
        self::assertTrue(BaseTypeAttribute::Storage->alterable());
        self::assertFalse(BaseTypeAttribute::Input->alterable());
        self::assertFalse(BaseTypeAttribute::Alignment->alterable());
    }

    public function testKindTypesEveryAttribute(): void
    {
        $kinds = array_map(static fn (BaseTypeAttribute $attribute): DefinitionKind => $attribute->kind(), BaseTypeAttribute::cases());
        self::assertSame([
            DefinitionKind::Type, DefinitionKind::Length, DefinitionKind::Name, DefinitionKind::Name, DefinitionKind::Name, DefinitionKind::Name, DefinitionKind::Name, DefinitionKind::Name, DefinitionKind::Name, DefinitionKind::Name,
            DefinitionKind::Text, DefinitionKind::Boolean, DefinitionKind::Text, DefinitionKind::Type, DefinitionKind::Text, DefinitionKind::Choice, DefinitionKind::Choice, DefinitionKind::Boolean, DefinitionKind::Boolean,
        ], $kinds);
    }
}
