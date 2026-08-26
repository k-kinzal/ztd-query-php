<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\ConstructorHydration;
use SqlFixture\Hydrator\DeclaredTypeCast;
use SqlFixture\Hydrator\HydrationException;
use SqlFixture\Hydrator\PropertyName;
use Tests\Fixture\Hydrator\TestEntity;
use Tests\Fixture\Hydrator\TestEntityWithCamelCase;
use Tests\Fixture\Hydrator\TestEntityWithDefaults;
use Tests\Fixture\Hydrator\TestEntityWithNullable;

#[CoversClass(ConstructorHydration::class)]
#[UsesClass(DeclaredTypeCast::class)]
#[UsesClass(HydrationException::class)]
#[UsesClass(PropertyName::class)]
final class ConstructorHydrationTest extends TestCase
{
    public function testHydrateFillsEachParameterFromTheColumnOfThatName(): void
    {
        $entity = (new ConstructorHydration())->hydrate(
            TestEntity::class,
            ['id' => 1, 'name' => 'Test'],
        );

        self::assertSame(1, $entity->id);
        self::assertSame('Test', $entity->name);
    }

    public function testHydrateFindsAColumnSpelledTheWayASchemaSpellsIt(): void
    {
        $entity = (new ConstructorHydration())->hydrate(
            TestEntityWithCamelCase::class,
            ['user_id' => 42, 'full_name' => 'John Doe'],
        );

        self::assertSame(42, $entity->userId);
        self::assertSame('John Doe', $entity->fullName);
    }

    public function testHydrateReadsAWireValueAsTheTypeItsParameterDeclares(): void
    {
        $entity = (new ConstructorHydration())->hydrate(
            TestEntity::class,
            ['id' => '7', 'name' => 'Test'],
        );

        self::assertSame(7, $entity->id);
    }

    public function testHydrateFallsBackToTheDefaultAParameterDeclares(): void
    {
        $entity = (new ConstructorHydration())->hydrate(
            TestEntityWithDefaults::class,
            ['id' => 1],
        );

        self::assertSame('default', $entity->name);
    }

    public function testHydrateGivesNullToANullableParameterTheRowSaysNothingAbout(): void
    {
        $entity = (new ConstructorHydration())->hydrate(
            TestEntityWithNullable::class,
            ['id' => 1],
        );

        self::assertNull($entity->name);
    }

    public function testHydrateReportsAParameterWithNowhereToGetAValue(): void
    {
        $this->expectException(HydrationException::class);
        $this->expectExceptionMessage('Missing required constructor parameter "name"');

        (new ConstructorHydration())->hydrate(TestEntity::class, ['id' => 1]);
    }
}
