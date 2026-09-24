<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionAttribute;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind;
use SqlSemantics\Model\Definition\TypeSystem\Definition\OperatorAttribute;

#[CoversClass(DefinitionAttribute::class)]
final class DefinitionAttributeTest extends TestCase
{
    public function testSpellingNamesEveryAttribute(): void
    {
        self::assertSame(['NEGATOR'], array_map(static fn (DefinitionAttribute $attribute): string => $attribute->spelling(), [OperatorAttribute::Negator]));
    }

    public function testKindTypesEveryAttribute(): void
    {
        self::assertSame([DefinitionKind::Operator], array_map(static fn (DefinitionAttribute $attribute): DefinitionKind => $attribute->kind(), [OperatorAttribute::Negator]));
    }

    public function testChooseSelectsNoKeywordForFreeArguments(): void
    {
        self::assertSame([null], array_map(static fn (DefinitionAttribute $attribute): ?string => $attribute->choose('x'), [OperatorAttribute::Negator]));
    }
}
