<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Schema\ColumnField;
use SqlSemantics\Type\Nullability;

#[CoversClass(ColumnField::class)]
#[Medium]
final class ColumnFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Field', 'Type', 'Collation', 'Null', 'Key', 'Default', 'Extra', 'Privileges', 'Comment'], array_map(static fn (ColumnField $field): string => $field->label(), ColumnField::cases()));
    }

    #[TestWith([ColumnField::Collation, Nullability::MaybeNull])]
    #[TestWith([ColumnField::Default, Nullability::MaybeNull])]
    #[TestWith([ColumnField::Name, Nullability::NotNull])]
    #[TestWith([ColumnField::Null, Nullability::NotNull])]
    #[TestWith([ColumnField::Comment, Nullability::NotNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(ColumnField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }

    public function testListingAddsCollationPrivilegesAndCommentOnlyWhenFull(): void
    {
        self::assertSame(['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'], array_column(ColumnField::listing(false), 'value'));
        self::assertSame(ColumnField::cases(), ColumnField::listing(true));
    }

    public function testTypeDefaultsToTextForEveryField(): void
    {
        self::assertSame(array_fill(0, count(ColumnField::cases()), 'varchar'), array_map(static fn (ColumnField $field): string => $field->type(), ColumnField::cases()));
    }
}
