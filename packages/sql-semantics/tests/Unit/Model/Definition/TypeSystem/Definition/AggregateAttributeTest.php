<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Definition\AggregateAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind;

#[CoversClass(AggregateAttribute::class)]
final class AggregateAttributeTest extends TestCase
{
    public function testSpellingIsTheSqlName(): void
    {
        self::assertSame('MFINALFUNC_EXTRA', AggregateAttribute::MfinalfuncExtra->spelling());
    }

    public function testKindTypesEachAttribute(): void
    {
        self::assertSame(DefinitionKind::Name, AggregateAttribute::Combinefunc->kind());
        self::assertSame(DefinitionKind::Type, AggregateAttribute::Mstype->kind());
        self::assertSame(DefinitionKind::Integer, AggregateAttribute::Sspace->kind());
        self::assertSame(DefinitionKind::Boolean, AggregateAttribute::Hypothetical->kind());
        self::assertSame(DefinitionKind::Text, AggregateAttribute::Minitcond->kind());
        self::assertSame(DefinitionKind::Operator, AggregateAttribute::Sortop->kind());
        self::assertSame(DefinitionKind::Choice, AggregateAttribute::FinalfuncModify->kind());
    }

    public function testChooseMatchesLowercaseKeywordsExactly(): void
    {
        self::assertSame('shareable', AggregateAttribute::MfinalfuncModify->choose('shareable'));
        self::assertNull(AggregateAttribute::MfinalfuncModify->choose('SHAREABLE'));
        self::assertSame('restricted', AggregateAttribute::Parallel->choose('restricted'));
        self::assertNull(AggregateAttribute::Sfunc->choose('safe'));
    }

    public function testMovingSelectsTheMovingAggregateAttributes(): void
    {
        self::assertTrue(AggregateAttribute::Minvfunc->moving());
        self::assertFalse(AggregateAttribute::Mstype->moving());
        self::assertFalse(AggregateAttribute::Sfunc->moving());
    }

    public function testKindCoversEveryAttribute(): void
    {
        self::assertSame(['Name', 'Type', 'Integer', 'Name', 'Boolean', 'Choice', 'Name', 'Name', 'Name', 'Text', 'Name', 'Name', 'Type', 'Integer', 'Name', 'Boolean', 'Choice', 'Text', 'Operator', 'Choice', 'Boolean'], array_map(static fn (AggregateAttribute $attribute): string => $attribute->kind()->name, AggregateAttribute::cases()));
    }
}
