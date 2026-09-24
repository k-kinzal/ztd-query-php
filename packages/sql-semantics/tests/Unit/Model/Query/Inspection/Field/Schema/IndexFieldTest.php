<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Schema\IndexField;
use SqlSemantics\Type\Nullability;

#[CoversClass(IndexField::class)]
#[Medium]
final class IndexFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Table', 'Non_unique', 'Key_name', 'Seq_in_index', 'Column_name', 'Collation', 'Cardinality', 'Sub_part', 'Packed', 'Null', 'Index_type', 'Comment', 'Index_comment', 'Visible', 'Expression'], array_map(static fn (IndexField $field): string => $field->label(), IndexField::cases()));
    }

    #[TestWith([IndexField::NonUnique, 'bigint'])]
    #[TestWith([IndexField::SequenceInIndex, 'bigint'])]
    #[TestWith([IndexField::Cardinality, 'bigint'])]
    #[TestWith([IndexField::SubPart, 'bigint'])]
    #[TestWith([IndexField::Table, 'varchar'])]
    #[TestWith([IndexField::Expression, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(IndexField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    #[TestWith([IndexField::ColumnName, Nullability::MaybeNull])]
    #[TestWith([IndexField::Collation, Nullability::MaybeNull])]
    #[TestWith([IndexField::Cardinality, Nullability::MaybeNull])]
    #[TestWith([IndexField::SubPart, Nullability::MaybeNull])]
    #[TestWith([IndexField::Packed, Nullability::MaybeNull])]
    #[TestWith([IndexField::Expression, Nullability::MaybeNull])]
    #[TestWith([IndexField::Table, Nullability::NotNull])]
    #[TestWith([IndexField::Visible, Nullability::NotNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(IndexField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }

    public function testListingOmitsVisibilityAndExpressionOnLegacyReleases(): void
    {
        self::assertSame(['Table', 'Non_unique', 'Key_name', 'Seq_in_index', 'Column_name', 'Collation', 'Cardinality', 'Sub_part', 'Packed', 'Null', 'Index_type', 'Comment', 'Index_comment'], array_column(IndexField::listing(true), 'value'));
        self::assertSame(IndexField::cases(), IndexField::listing(false));
    }
}
