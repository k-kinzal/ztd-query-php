<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Session\VariableField;
use SqlSemantics\Type\Nullability;

#[CoversClass(VariableField::class)]
#[Medium]
final class VariableFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Variable_name', 'Value'], array_map(static fn (VariableField $field): string => $field->label(), VariableField::cases()));
    }

    #[TestWith([VariableField::Name, 'varchar'])]
    #[TestWith([VariableField::Value, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(VariableField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testNullabilityDefaultsToPresent(): void
    {
        self::assertSame(Nullability::NotNull, VariableField::cases()[0]->nullability());
    }
}
