<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\DeclaredTypeCast;
use SqlFixture\Hydrator\PropertyHydration;
use SqlFixture\Hydrator\PropertyName;
use Tests\Fixture\Hydrator\TestEntityViaProperties;
use Tests\Fixture\Hydrator\TestEntityViaPropertiesCamel;
use Tests\Fixture\Hydrator\TestEntityWithoutConstructor;

#[CoversClass(PropertyHydration::class)]
#[UsesClass(DeclaredTypeCast::class)]
#[UsesClass(PropertyName::class)]
final class PropertyHydrationTest extends TestCase
{
    public function testHydrateAssignsEachColumnOntoThePropertyOfThatName(): void
    {
        $entity = (new PropertyHydration())->hydrate(
            TestEntityWithoutConstructor::class,
            ['id' => 1, 'name' => 'Test'],
        );

        self::assertSame(1, $entity->id);
        self::assertSame('Test', $entity->name);
    }

    public function testHydrateFindsAPropertySpelledTheWayPhpSpellsIt(): void
    {
        $entity = (new PropertyHydration())->hydrate(
            TestEntityViaPropertiesCamel::class,
            ['user_name' => 'John'],
        );

        self::assertSame('John', $entity->userName);
    }

    public function testHydrateReadsAWireValueAsTheTypeItsPropertyDeclares(): void
    {
        $entity = (new PropertyHydration())->hydrate(
            TestEntityViaProperties::class,
            ['id' => '7', 'amount' => '1.5', 'active' => '1'],
        );

        self::assertSame(7, $entity->id);
        self::assertSame(1.5, $entity->amount);
        self::assertTrue($entity->active);
    }

    public function testHydratePassesOverAColumnTheObjectModelsNothingFor(): void
    {
        $entity = (new PropertyHydration())->hydrate(
            TestEntityWithoutConstructor::class,
            ['id' => 1, 'name' => 'Test', 'no_such_column' => 'x'],
        );

        self::assertSame(1, $entity->id);
    }

    public function testPropertyForAnswersThePropertyAColumnIsAssignedTo(): void
    {
        $property = (new PropertyHydration())->propertyFor(
            TestEntityViaPropertiesCamel::class,
            'user_name',
        );

        self::assertNotNull($property);
        self::assertSame('userName', $property->getName());
    }

    public function testPropertyForAnswersNothingForAColumnTheObjectModelsNothingFor(): void
    {
        self::assertNull((new PropertyHydration())->propertyFor(
            TestEntityViaPropertiesCamel::class,
            'no_such_column',
        ));
    }
}
