<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOptions;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(DefinitionOptions::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DefinitionOptionsTest extends TestCase
{
    public function testValidateRejectsARepeatedAttribute(): void
    {
        $this->expectException(InvalidStructure::class);
        DefinitionOptions::validate([new DefinitionOption(OperatorAttribute::Hashes, true), new DefinitionOption(OperatorAttribute::Hashes, false)], OperatorAttribute::cases());
    }

    public function testValidateRejectsAnAttributeOutsideTheAllowedSet(): void
    {
        $this->expectException(InvalidStructure::class);
        DefinitionOptions::validate([new DefinitionOption(OperatorAttribute::Hashes, true)], [OperatorAttribute::Merges]);
    }

    public function testRequireRejectsAMissingAttribute(): void
    {
        DefinitionOptions::require([new DefinitionOption(OperatorAttribute::Hashes, true)], [OperatorAttribute::Hashes]);
        $this->expectException(InvalidStructure::class);
        DefinitionOptions::require([new DefinitionOption(OperatorAttribute::Hashes, true)], [OperatorAttribute::Function]);
    }

    public function testPresentRejectsNone(): void
    {
        $this->expectException(InvalidStructure::class);
        DefinitionOptions::present([new DefinitionOption(OperatorAttribute::Join, null)]);
    }

    public function testFindReturnsTheOptionOfAnAttribute(): void
    {
        $option = new DefinitionOption(OperatorAttribute::Join, new QualifiedName(['eqjoinsel']));
        self::assertSame($option, DefinitionOptions::find([$option], OperatorAttribute::Join));
        self::assertNull(DefinitionOptions::find([$option], OperatorAttribute::Restrict));
    }

    public function testValueReturnsTheArgumentOfAnAttribute(): void
    {
        self::assertTrue(DefinitionOptions::value([new DefinitionOption(OperatorAttribute::Merges, true)], OperatorAttribute::Merges));
        self::assertNull(DefinitionOptions::value([], OperatorAttribute::Merges));
    }
}
