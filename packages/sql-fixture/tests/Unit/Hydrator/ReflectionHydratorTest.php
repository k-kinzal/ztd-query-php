<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\HydrationException;
use SqlFixture\Hydrator\ReflectionHydrator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Fixture\Hydrator\TestEntity;
use Tests\Fixture\Hydrator\TestEntityNoParams;
use Tests\Fixture\Hydrator\TestEntityViaProperties;
use Tests\Fixture\Hydrator\TestEntityViaPropertiesCamel;
use Tests\Fixture\Hydrator\TestEntityViaPropertiesMixed;
use Tests\Fixture\Hydrator\TestEntityWithArray;
use Tests\Fixture\Hydrator\TestEntityWithBool;
use Tests\Fixture\Hydrator\TestEntityWithCamelCase;
use Tests\Fixture\Hydrator\TestEntityWithDefaults;
use Tests\Fixture\Hydrator\TestEntityWithFloat;
use Tests\Fixture\Hydrator\TestEntityWithMixed;
use Tests\Fixture\Hydrator\TestEntityWithNullable;
use Tests\Fixture\Hydrator\TestEntityWithoutConstructor;
use Tests\Fixture\Hydrator\TestEntityWithPropertyMapping;
use Tests\Fixture\Hydrator\TestEntityWithString;

#[CoversClass(ReflectionHydrator::class)]
#[UsesClass(HydrationException::class)]
final class ReflectionHydratorTest extends TestCase
{
    #[Test]
    public function hydrateViaConstructor(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntity::class);

        self::assertInstanceOf(TestEntity::class, $object);
        self::assertSame(1, $object->id);
        self::assertSame('Test', $object->name);
    }

    #[Test]
    public function hydrateViaProperties(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithoutConstructor::class);

        self::assertInstanceOf(TestEntityWithoutConstructor::class, $object);
        self::assertSame(1, $object->id);
        self::assertSame('Test', $object->name);
    }

    #[Test]
    public function hydrateWithSnakeCaseToConstructor(): void
    {
        $data = ['user_id' => 42, 'full_name' => 'John Doe'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithCamelCase::class);

        self::assertInstanceOf(TestEntityWithCamelCase::class, $object);
        self::assertSame(42, $object->userId);
        self::assertSame('John Doe', $object->fullName);
    }

    #[Test]
    public function hydrateWithDefaultValues(): void
    {
        $data = ['id' => 1];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithDefaults::class);

        self::assertInstanceOf(TestEntityWithDefaults::class, $object);
        self::assertSame(1, $object->id);
        self::assertSame('default', $object->name);
    }

    #[Test]
    public function hydrateWithNullableParameter(): void
    {
        $data = ['id' => 1];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithNullable::class);

        self::assertInstanceOf(TestEntityWithNullable::class, $object);
        self::assertSame(1, $object->id);
        self::assertNull($object->name);
    }

    #[Test]
    public function throwsExceptionForMissingRequiredParameter(): void
    {
        $this->expectException(HydrationException::class);
        (new ReflectionHydrator())->hydrate(['name' => 'Test'], TestEntity::class);
    }

    #[Test]
    public function throwsExceptionForNonExistentClass(): void
    {
        $hydrator = new ReflectionHydrator();
        self::expectException(HydrationException::class);
        /** @var class-string<object> $class */
        $class = 'NonExistentClass';
        $hydrator->hydrate([], $class);
    }

    #[Test]
    public function castsIntValue(): void
    {
        $data = ['id' => '42', 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntity::class);

        self::assertSame(42, $object->id);
        self::assertIsInt($object->id);
        self::assertNotSame('42', $object->id);
    }

    #[Test]
    public function castsFloatValue(): void
    {
        $data = ['amount' => '99.99'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithFloat::class);

        self::assertSame(99.99, $object->amount);
        self::assertIsFloat($object->amount);
        self::assertNotSame('99.99', $object->amount);
    }

    #[Test]
    public function castsBoolValue(): void
    {
        $data = ['active' => 1];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithBool::class);

        self::assertTrue($object->active);
        self::assertIsBool($object->active);
        self::assertNotSame(1, $object->active);
    }

    #[Test]
    public function castsBoolFalseValue(): void
    {
        $data = ['active' => 0];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithBool::class);

        self::assertFalse($object->active);
        self::assertIsBool($object->active);
    }

    #[Test]
    public function castsArrayValue(): void
    {
        $data = ['items' => '["a","b","c"]'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithArray::class);

        self::assertSame(['a', 'b', 'c'], $object->items);
        self::assertIsArray($object->items);
    }

    #[Test]
    public function castsArrayFromNonJsonString(): void
    {
        $data = ['items' => 'single_value'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithArray::class);

        self::assertSame(['single_value'], $object->items);
        self::assertIsArray($object->items);
    }

    #[Test]
    public function castsStringValue(): void
    {
        $data = ['value' => 123];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithString::class);

        self::assertSame('123', $object->value);
        self::assertIsString($object->value);
        self::assertNotSame(123, $object->value);
    }

    #[Test]
    public function hydrateWithSnakeCaseProperties(): void
    {
        $data = ['user_name' => 'John'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithPropertyMapping::class);

        self::assertSame('John', $object->userName);
    }

    #[Test]
    public function hydrateIgnoresExtraData(): void
    {
        $data = ['id' => 1, 'name' => 'Test', 'extra' => 'ignored'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntity::class);

        self::assertSame(1, $object->id);
        self::assertSame('Test', $object->name);
    }

    #[Test]
    public function hydrateWithMixedType(): void
    {
        $data = ['value' => ['nested' => 'data']];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithMixed::class);

        self::assertSame(['nested' => 'data'], $object->value);
    }

    #[Test]
    public function hydrateViaPropertiesWithCasting(): void
    {
        $data = ['id' => '42', 'name' => 123, 'amount' => '9.5', 'active' => 1];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaProperties::class);

        self::assertSame(42, $object->id);
        self::assertSame('123', $object->name);
        self::assertSame(9.5, $object->amount);
        self::assertTrue($object->active);
    }

    #[Test]
    public function hydrateViaPropertiesIgnoresUnknownKeys(): void
    {
        $data = ['unknown_key' => 'value'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaProperties::class);

        self::assertSame(0, $object->id);
    }

    #[Test]
    public function hydrateViaPropertiesSkipsUnknownAndContinues(): void
    {
        $data = ['unknown_key' => 'value', 'id' => 42, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaProperties::class);

        self::assertSame(42, $object->id);
        self::assertSame('Test', $object->name);
    }

    #[Test]
    public function hydrateViaPropertiesWithSnakeCase(): void
    {
        $data = ['user_name' => 'John'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaPropertiesCamel::class);

        self::assertSame('John', $object->userName);
    }

    #[Test]
    public function hydrateViaPropertiesDirectKey(): void
    {
        $data = ['userName' => 'Direct'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaPropertiesCamel::class);

        self::assertSame('Direct', $object->userName);
    }

    #[Test]
    public function hydrateViaConstructorWithEmptyConstructor(): void
    {
        $data = ['id' => 5, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityNoParams::class);

        self::assertSame(5, $object->id);
        self::assertSame('Test', $object->name);
    }

    #[Test]
    public function castNullReturnsNull(): void
    {
        $data = ['id' => 1, 'name' => null];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithNullable::class);

        self::assertNull($object->name);
    }

    #[Test]
    public function castArrayFromAlreadyArray(): void
    {
        $data = ['items' => ['existing', 'array']];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithArray::class);

        self::assertSame(['existing', 'array'], $object->items);
    }

    #[Test]
    public function castNonNumericToIntViaProperties(): void
    {
        $data = ['id' => 'not_a_number'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaPropertiesMixed::class);

        self::assertSame('not_a_number', $object->id);
    }

    #[Test]
    public function castNonNumericToFloatViaProperties(): void
    {
        $data = ['amount' => 'not_a_number'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaPropertiesMixed::class);

        self::assertSame('not_a_number', $object->amount);
    }

    #[Test]
    public function castNonScalarToStringViaProperties(): void
    {
        $data = ['name' => ['array_value']];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaPropertiesMixed::class);

        self::assertSame(['array_value'], $object->name);
    }

    #[Test]
    public function castIntToArrayViaCastValue(): void
    {
        $data = ['items' => 42];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithArray::class);

        self::assertSame([42], $object->items);
    }

    #[Test]
    public function castBoolToArrayViaCastValue(): void
    {
        $data = ['items' => true];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithArray::class);

        self::assertSame([true], $object->items);
    }

    #[Test]
    public function castsIntValueFromNumericString(): void
    {
        $data = ['id' => '123', 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntity::class);

        self::assertSame(123, $object->id);
    }

    #[Test]
    public function castsFloatValueFromNumericString(): void
    {
        $data = ['amount' => '3.14'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithFloat::class);

        self::assertSame(3.14, $object->amount);
    }

    #[Test]
    public function castsStringFromIntValue(): void
    {
        $data = ['value' => 42];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithString::class);

        self::assertSame('42', $object->value);
    }

    #[Test]
    public function castsBoolFromTruthyValue(): void
    {
        $data = ['active' => 'yes'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithBool::class);

        self::assertTrue($object->active);
    }

    #[Test]
    public function castsBoolFromFalsyValue(): void
    {
        $data = ['active' => ''];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithBool::class);

        self::assertFalse($object->active);
    }

    #[Test]
    public function castIntFromFloat(): void
    {
        $data = ['id' => 7.9, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntity::class);

        self::assertSame(7, $object->id);
    }

    #[Test]
    public function castFloatFromInt(): void
    {
        $data = ['amount' => 10];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithFloat::class);

        self::assertSame(10.0, $object->amount);
    }

    #[Test]
    public function castStringFromBool(): void
    {
        $data = ['value' => true];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithString::class);

        self::assertSame('1', $object->value);
    }

    #[Test]
    public function castStringFromFloat(): void
    {
        $data = ['value' => 3.14];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithString::class);

        self::assertSame('3.14', $object->value);
    }
}
