<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Setting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Setting\SettingField;
use SqlSemantics\Type\Nullability;

#[CoversClass(SettingField::class)]
final class SettingFieldTest extends TestCase
{
    #[TestWith([SettingField::Name, Nullability::NotNull])]
    #[TestWith([SettingField::Setting, Nullability::NotNull])]
    #[TestWith([SettingField::Description, Nullability::MaybeNull])]
    public function testNullabilityFollowsTheFieldRole(SettingField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }
}
