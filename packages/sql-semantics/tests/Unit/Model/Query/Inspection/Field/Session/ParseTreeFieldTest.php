<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Session\ParseTreeField;
use SqlSemantics\Type\Nullability;

#[CoversClass(ParseTreeField::class)]
#[Medium]
final class ParseTreeFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Parse_tree'], array_map(static fn (ParseTreeField $field): string => $field->label(), ParseTreeField::cases()));
    }

    #[TestWith([ParseTreeField::Tree, 'json'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(ParseTreeField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(ParseTreeField::cases()), Nullability::NotNull), array_map(static fn (ParseTreeField $field): Nullability => $field->nullability(), ParseTreeField::cases()));
    }
}
