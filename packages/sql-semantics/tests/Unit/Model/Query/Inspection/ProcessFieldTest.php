<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\ProcessField;
use SqlSemantics\Type\Nullability;

#[CoversClass(ProcessField::class)]
#[Medium]
final class ProcessFieldTest extends TestCase
{
    #[TestWith([ProcessField::Connection, Nullability::NotNull])]
    #[TestWith([ProcessField::User, Nullability::NotNull])]
    #[TestWith([ProcessField::Host, Nullability::NotNull])]
    #[TestWith([ProcessField::Database, Nullability::MaybeNull])]
    #[TestWith([ProcessField::Command, Nullability::NotNull])]
    #[TestWith([ProcessField::ElapsedTime, Nullability::NotNull])]
    #[TestWith([ProcessField::State, Nullability::MaybeNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(ProcessField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }

}
