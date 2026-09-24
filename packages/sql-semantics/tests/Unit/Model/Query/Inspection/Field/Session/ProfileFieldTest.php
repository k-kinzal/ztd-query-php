<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Session\ProfileField;
use SqlSemantics\Model\Query\Inspection\Profile\ProfileCategory;
use SqlSemantics\Type\Nullability;

#[CoversClass(ProfileField::class)]
#[Medium]
final class ProfileFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Query_ID', 'Duration', 'Query', 'Status', 'CPU_user', 'CPU_system', 'Context_voluntary', 'Context_involuntary', 'Block_ops_in', 'Block_ops_out', 'Messages_sent', 'Messages_received', 'Page_faults_major', 'Page_faults_minor', 'Swaps', 'Source_function', 'Source_file', 'Source_line'], array_map(static fn (ProfileField $field): string => $field->label(), ProfileField::cases()));
    }

    #[TestWith([ProfileField::QueryId, 'bigint'])]
    #[TestWith([ProfileField::SourceLine, 'bigint'])]
    #[TestWith([ProfileField::Swaps, 'bigint'])]
    #[TestWith([ProfileField::Duration, 'numeric'])]
    #[TestWith([ProfileField::CpuUser, 'numeric'])]
    #[TestWith([ProfileField::CpuSystem, 'numeric'])]
    #[TestWith([ProfileField::Query, 'varchar'])]
    #[TestWith([ProfileField::Status, 'varchar'])]
    #[TestWith([ProfileField::SourceFile, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(ProfileField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    #[TestWith([ProfileField::QueryId, Nullability::NotNull])]
    #[TestWith([ProfileField::Duration, Nullability::NotNull])]
    #[TestWith([ProfileField::Query, Nullability::NotNull])]
    #[TestWith([ProfileField::Status, Nullability::NotNull])]
    #[TestWith([ProfileField::CpuUser, Nullability::MaybeNull])]
    #[TestWith([ProfileField::Swaps, Nullability::MaybeNull])]
    #[TestWith([ProfileField::SourceLine, Nullability::MaybeNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(ProfileField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }

    public function testSummaryListsTheProfiledStatementFields(): void
    {
        self::assertSame(['Query_ID', 'Duration', 'Query'], array_column(ProfileField::summary(), 'value'));
    }

    public function testDetailAppendsEachMeasuredCategoryInServerOrder(): void
    {
        self::assertSame(['Status', 'Duration'], array_column(ProfileField::detail([]), 'value'));
        self::assertSame(['Status', 'Duration', 'CPU_user', 'CPU_system', 'Swaps'], array_column(ProfileField::detail([ProfileCategory::Swaps, ProfileCategory::Cpu, ProfileCategory::Memory]), 'value'));
        self::assertSame(['Status', 'Duration', 'CPU_user', 'CPU_system', 'Context_voluntary', 'Context_involuntary', 'Block_ops_in', 'Block_ops_out', 'Messages_sent', 'Messages_received', 'Page_faults_major', 'Page_faults_minor', 'Swaps', 'Source_function', 'Source_file', 'Source_line'], array_column(ProfileField::detail([ProfileCategory::All]), 'value'));
    }

    public function testTypeMapsDurationsToABuiltinNumericIdentity(): void
    {
        self::assertSame('numeric', \SqlSemantics\Type\TypeDescriptor::builtin(\SqlSemantics\Dialect::MySql, ProfileField::Duration->type())->name);
    }

    public function testTypeCoversEveryField(): void
    {
        self::assertSame(['bigint', 'numeric', 'varchar', 'varchar', 'numeric', 'numeric', 'bigint', 'bigint', 'bigint', 'bigint', 'bigint', 'bigint', 'bigint', 'bigint', 'bigint', 'varchar', 'varchar', 'bigint'], array_map(static fn (ProfileField $field): string => $field->type(), ProfileField::cases()));
    }

    public function testNullabilityCoversEveryField(): void
    {
        self::assertSame(['not-null', 'not-null', 'not-null', 'not-null', 'maybe-null', 'maybe-null', 'maybe-null', 'maybe-null', 'maybe-null', 'maybe-null', 'maybe-null', 'maybe-null', 'maybe-null', 'maybe-null', 'maybe-null', 'maybe-null', 'maybe-null', 'maybe-null'], array_map(static fn (ProfileField $field): string => $field->nullability()->value, ProfileField::cases()));
    }

    public function testDetailAddsNoFieldForMemory(): void
    {
        self::assertSame(['Status', 'Duration', 'Block_ops_in', 'Block_ops_out'], array_column(ProfileField::detail([ProfileCategory::Memory, ProfileCategory::BlockIo]), 'value'));
    }
}
