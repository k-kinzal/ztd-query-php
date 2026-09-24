<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Schema\CharacterSetField;
use SqlSemantics\Type\Nullability;

#[CoversClass(CharacterSetField::class)]
#[Medium]
final class CharacterSetFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Charset', 'Description', 'Default collation', 'Maxlen'], array_map(static fn (CharacterSetField $field): string => $field->label(), CharacterSetField::cases()));
    }

    #[TestWith([CharacterSetField::MaximumLength, 'bigint'])]
    #[TestWith([CharacterSetField::Name, 'varchar'])]
    #[TestWith([CharacterSetField::DefaultCollation, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(CharacterSetField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CharacterSetField::cases()), Nullability::NotNull), array_map(static fn (CharacterSetField $field): Nullability => $field->nullability(), CharacterSetField::cases()));
    }
}
