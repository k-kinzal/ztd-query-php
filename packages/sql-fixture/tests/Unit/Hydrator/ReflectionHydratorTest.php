<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlFixture\Hydrator\HydrationException;
use SqlFixture\Hydrator\ReflectionHydrator;
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
#[CoversClass(\SqlFixture\Hydrator\Reflection\ConstructorHydration::class)]
#[CoversClass(\SqlFixture\Hydrator\Reflection\PropertyHydration::class)]
#[CoversClass(\SqlFixture\Hydrator\Reflection\PropertyNames::class)]
#[CoversClass(\SqlFixture\Hydrator\Reflection\ValueConversion::class)]
#[UsesClass(\SqlFixture\Hydrator\HydratorInterface::class)]
final class ReflectionHydratorTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaConstructor(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntity::class);

        self::assertSame(1, $object->id);
        self::assertSame('Test', $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaProperties(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithoutConstructor::class);

        self::assertSame(1, $object->id);
        self::assertSame('Test', $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateWithSnakeCaseToConstructor(): void
    {
        $data = ['user_id' => 42, 'full_name' => 'John Doe'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithCamelCase::class);

        self::assertSame(42, $object->userId);
        self::assertSame('John Doe', $object->fullName);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateWithDefaultValues(): void
    {
        $data = ['id' => 1];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithDefaults::class);

        self::assertSame(1, $object->id);
        self::assertSame('default', $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateWithNullableParameter(): void
    {
        $data = ['id' => 1];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithNullable::class);

        self::assertSame(1, $object->id);
        self::assertNull($object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testThrowsExceptionForMissingRequiredParameter(): void
    {
        $this->expectException(HydrationException::class);
        (new ReflectionHydrator())->hydrate(['name' => 'Test'], TestEntity::class);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testThrowsExceptionForNonExistentClass(): void
    {
        $hydrator = new ReflectionHydrator();
        self::expectException(HydrationException::class);
        /**
         * @var class-string<object> $class
         */
        $class = 'NonExistentClass';
        $hydrator->hydrate([], $class);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsIntValue(): void
    {
        $data = ['id' => '42', 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntity::class);

        self::assertSame(42, $object->id);
        self::assertIsInt($object->id);
        self::assertNotSame('42', $object->id);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsFloatValue(): void
    {
        $data = ['amount' => '99.99'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithFloat::class);

        self::assertSame(99.99, $object->amount);
        self::assertIsFloat($object->amount);
        self::assertNotSame('99.99', $object->amount);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsBoolValue(): void
    {
        $data = ['active' => 1];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithBool::class);

        self::assertTrue($object->active);
        self::assertIsBool($object->active);
        self::assertNotSame(1, $object->active);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsBoolFalseValue(): void
    {
        $data = ['active' => 0];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithBool::class);

        self::assertFalse($object->active);
        self::assertIsBool($object->active);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsArrayValue(): void
    {
        $data = ['items' => '["a","b","c"]'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithArray::class);

        self::assertSame(['a', 'b', 'c'], $object->items);
        self::assertIsArray($object->items);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsArrayFromNonJsonString(): void
    {
        $data = ['items' => 'single_value'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithArray::class);

        self::assertSame(['single_value'], $object->items);
        self::assertIsArray($object->items);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsStringValue(): void
    {
        $data = ['value' => 123];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithString::class);

        self::assertSame('123', $object->value);
        self::assertIsString($object->value);
        self::assertNotSame(123, $object->value);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateWithSnakeCaseProperties(): void
    {
        $data = ['user_name' => 'John'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithPropertyMapping::class);

        self::assertSame('John', $object->userName);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateIgnoresExtraData(): void
    {
        $data = ['id' => 1, 'name' => 'Test', 'extra' => 'ignored'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntity::class);

        self::assertSame(1, $object->id);
        self::assertSame('Test', $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateWithMixedType(): void
    {
        $data = ['value' => ['nested' => 'data']];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithMixed::class);

        self::assertSame(['nested' => 'data'], $object->value);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaPropertiesWithCasting(): void
    {
        $data = ['id' => '42', 'name' => 123, 'amount' => '9.5', 'active' => 1];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaProperties::class);

        self::assertSame(42, $object->id);
        self::assertSame('123', $object->name);
        self::assertSame(9.5, $object->amount);
        self::assertTrue($object->active);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaPropertiesIgnoresUnknownKeys(): void
    {
        $data = ['unknown_key' => 'value'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaProperties::class);

        self::assertSame(0, $object->id);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaPropertiesSkipsUnknownAndContinues(): void
    {
        $data = ['unknown_key' => 'value', 'id' => 42, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaProperties::class);

        self::assertSame(42, $object->id);
        self::assertSame('Test', $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaPropertiesWithSnakeCase(): void
    {
        $data = ['user_name' => 'John'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaPropertiesCamel::class);

        self::assertSame('John', $object->userName);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaPropertiesDirectKey(): void
    {
        $data = ['userName' => 'Direct'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaPropertiesCamel::class);

        self::assertSame('Direct', $object->userName);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaConstructorWithEmptyConstructor(): void
    {
        $data = ['id' => 5, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityNoParams::class);

        self::assertSame(5, $object->id);
        self::assertSame('Test', $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastNullReturnsNull(): void
    {
        $data = ['id' => 1, 'name' => null];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithNullable::class);

        self::assertNull($object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastArrayFromAlreadyArray(): void
    {
        $data = ['items' => ['existing', 'array']];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithArray::class);

        self::assertSame(['existing', 'array'], $object->items);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastNonNumericToIntViaProperties(): void
    {
        $data = ['id' => 'not_a_number'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaPropertiesMixed::class);

        self::assertSame('not_a_number', $object->id);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastNonNumericToFloatViaProperties(): void
    {
        $data = ['amount' => 'not_a_number'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaPropertiesMixed::class);

        self::assertSame('not_a_number', $object->amount);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastNonScalarToStringViaProperties(): void
    {
        $data = ['name' => ['array_value']];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityViaPropertiesMixed::class);

        self::assertSame(['array_value'], $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastIntToArrayViaCastValue(): void
    {
        $data = ['items' => 42];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithArray::class);

        self::assertSame([42], $object->items);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastBoolToArrayViaCastValue(): void
    {
        $data = ['items' => true];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithArray::class);

        self::assertSame([true], $object->items);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsIntValueFromNumericString(): void
    {
        $data = ['id' => '123', 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntity::class);

        self::assertSame(123, $object->id);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsFloatValueFromNumericString(): void
    {
        $data = ['amount' => '3.14'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithFloat::class);

        self::assertSame(3.14, $object->amount);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsStringFromIntValue(): void
    {
        $data = ['value' => 42];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithString::class);

        self::assertSame('42', $object->value);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsBoolFromTruthyValue(): void
    {
        $data = ['active' => 'yes'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithBool::class);

        self::assertTrue($object->active);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsBoolFromFalsyValue(): void
    {
        $data = ['active' => ''];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithBool::class);

        self::assertFalse($object->active);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastIntFromFloat(): void
    {
        $data = ['id' => 7.9, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntity::class);

        self::assertSame(7, $object->id);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastFloatFromInt(): void
    {
        $data = ['amount' => 10];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithFloat::class);

        self::assertSame(10.0, $object->amount);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastStringFromBool(): void
    {
        $data = ['value' => true];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithString::class);

        self::assertSame('1', $object->value);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastStringFromFloat(): void
    {
        $data = ['value' => 3.14];
        $object = (new ReflectionHydrator())->hydrate($data, TestEntityWithString::class);

        self::assertSame('3.14', $object->value);
    }
}
