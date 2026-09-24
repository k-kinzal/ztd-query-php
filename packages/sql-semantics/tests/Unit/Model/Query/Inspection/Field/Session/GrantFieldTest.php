<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Session\GrantField;
use SqlSemantics\Type\Nullability;

#[CoversClass(GrantField::class)]
#[Medium]
final class GrantFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Grants for '], array_map(static fn (GrantField $field): string => $field->label(), GrantField::cases()));
    }

    #[TestWith([GrantField::Grants, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(GrantField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testNullabilityDefaultsToPresent(): void
    {
        self::assertSame(Nullability::NotNull, GrantField::cases()[0]->nullability());
    }
}
