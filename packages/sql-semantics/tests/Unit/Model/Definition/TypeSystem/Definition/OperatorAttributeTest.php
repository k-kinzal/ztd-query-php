<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;

#[CoversClass(OperatorAttribute::class)]
final class OperatorAttributeTest extends TestCase
{
    public function testSpellingIsTheSqlName(): void
    {
        self::assertSame('LEFTARG', OperatorAttribute::LeftArg->spelling());
    }

    public function testKindTypesEachAttribute(): void
    {
        self::assertSame(DefinitionKind::Name, OperatorAttribute::Join->kind());
        self::assertSame(DefinitionKind::Type, OperatorAttribute::RightArg->kind());
        self::assertSame(DefinitionKind::Operator, OperatorAttribute::Commutator->kind());
        self::assertSame(DefinitionKind::Boolean, OperatorAttribute::Merges->kind());
    }

    public function testChooseHasNoKeywords(): void
    {
        self::assertNull(OperatorAttribute::Hashes->choose('true'));
    }

    public function testAlterableExcludesTheFunctionAndOperands(): void
    {
        self::assertFalse(OperatorAttribute::Function->alterable());
        self::assertFalse(OperatorAttribute::LeftArg->alterable());
        self::assertTrue(OperatorAttribute::Restrict->alterable());
    }
}
