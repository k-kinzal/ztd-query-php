<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Replication\BinaryLogField;
use SqlSemantics\Type\Nullability;

#[CoversClass(BinaryLogField::class)]
#[Medium]
final class BinaryLogFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Log_name', 'File_size', 'Encrypted'], array_map(static fn (BinaryLogField $field): string => $field->label(), BinaryLogField::cases()));
    }

    #[TestWith([BinaryLogField::Size, 'bigint'])]
    #[TestWith([BinaryLogField::Encrypted, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(BinaryLogField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testNullabilityDefaultsToPresent(): void
    {
        self::assertSame(Nullability::NotNull, BinaryLogField::cases()[0]->nullability());
    }
}
