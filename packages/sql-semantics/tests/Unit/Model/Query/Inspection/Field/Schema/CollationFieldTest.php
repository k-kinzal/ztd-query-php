<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Schema\CollationField;
use SqlSemantics\Type\Nullability;

#[CoversClass(CollationField::class)]
#[Medium]
final class CollationFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Collation', 'Charset', 'Id', 'Default', 'Compiled', 'Sortlen', 'Pad_attribute'], array_map(static fn (CollationField $field): string => $field->label(), CollationField::cases()));
    }

    #[TestWith([CollationField::Id, 'bigint'])]
    #[TestWith([CollationField::SortLength, 'bigint'])]
    #[TestWith([CollationField::Name, 'varchar'])]
    #[TestWith([CollationField::PadAttribute, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(CollationField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testListingOmitsThePadAttributeOnLegacyReleases(): void
    {
        self::assertSame(['Collation', 'Charset', 'Id', 'Default', 'Compiled', 'Sortlen'], array_column(CollationField::listing(true), 'value'));
        self::assertSame(CollationField::cases(), CollationField::listing(false));
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(CollationField::cases()), Nullability::NotNull), array_map(static fn (CollationField $field): Nullability => $field->nullability(), CollationField::cases()));
    }
}
