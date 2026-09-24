<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Replication\BinaryLogStatusField;
use SqlSemantics\Type\Nullability;

#[CoversClass(BinaryLogStatusField::class)]
#[Medium]
final class BinaryLogStatusFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['File', 'Position', 'Binlog_Do_DB', 'Binlog_Ignore_DB', 'Executed_Gtid_Set'], array_map(static fn (BinaryLogStatusField $field): string => $field->label(), BinaryLogStatusField::cases()));
    }

    #[TestWith([BinaryLogStatusField::Position, 'bigint'])]
    #[TestWith([BinaryLogStatusField::File, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(BinaryLogStatusField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testNullabilityDefaultsToPresent(): void
    {
        self::assertSame(Nullability::NotNull, BinaryLogStatusField::cases()[0]->nullability());
    }
}
