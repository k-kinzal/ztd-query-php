<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\PrivilegeField;
use SqlSemantics\Type\Nullability;

#[CoversClass(PrivilegeField::class)]
#[Medium]
final class PrivilegeFieldTest extends TestCase
{
    #[TestWith([PrivilegeField::Name, Nullability::NotNull])]
    #[TestWith([PrivilegeField::Context, Nullability::NotNull])]
    #[TestWith([PrivilegeField::Description, Nullability::NotNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(PrivilegeField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }

}
