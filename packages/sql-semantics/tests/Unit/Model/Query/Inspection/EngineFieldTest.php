<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\EngineField;
use SqlSemantics\Type\Nullability;

#[CoversClass(EngineField::class)]
#[Medium]
final class EngineFieldTest extends TestCase
{
    #[TestWith([EngineField::Name, Nullability::NotNull])]
    #[TestWith([EngineField::Support, Nullability::NotNull])]
    #[TestWith([EngineField::Description, Nullability::NotNull])]
    #[TestWith([EngineField::Transactions, Nullability::MaybeNull])]
    #[TestWith([EngineField::Xa, Nullability::MaybeNull])]
    #[TestWith([EngineField::Savepoints, Nullability::MaybeNull])]
    public function testNullabilityRetainsTheDeclaredMetadataFact(EngineField $field, Nullability $expected): void
    {
        self::assertSame($expected, $field->nullability());
    }

}
