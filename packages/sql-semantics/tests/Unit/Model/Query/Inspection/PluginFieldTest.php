<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\PluginField;
use SqlSemantics\Type\Nullability;

#[CoversClass(PluginField::class)]
#[Medium]
final class PluginFieldTest extends TestCase
{
    #[TestWith([PluginField::Name, Nullability::NotNull])]
    #[TestWith([PluginField::Status, Nullability::NotNull])]
    #[TestWith([PluginField::Type, Nullability::NotNull])]
    #[TestWith([PluginField::Library, Nullability::MaybeNull])]
    #[TestWith([PluginField::License, Nullability::MaybeNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(PluginField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }

}
