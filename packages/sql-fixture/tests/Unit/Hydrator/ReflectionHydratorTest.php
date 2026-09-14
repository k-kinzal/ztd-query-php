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

#[CoversClass(ReflectionHydrator::class)]
#[UsesClass(HydrationException::class)]
#[CoversClass(\SqlFixture\Hydrator\Reflection\ConstructorHydration::class)]
#[CoversClass(\SqlFixture\Hydrator\Reflection\PropertyHydration::class)]
#[CoversClass(\SqlFixture\Hydrator\Reflection\PropertyNames::class)]
#[CoversClass(\SqlFixture\Hydrator\Reflection\ValueConversion::class)]
#[UsesClass(\SqlFixture\Hydrator\HydratorInterface::class)]
#[UsesClass(\SqlFixture\Hydrator\Exception\ClassNotFoundException::class)]
#[UsesClass(\SqlFixture\Hydrator\Exception\MissingConstructorArgumentException::class)]
final class ReflectionHydratorTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaConstructor(): void
    {
        $target = new class (0, '') {
            public function __construct(
                public readonly int $id,
                public readonly string $name,
            ) {
            }
        };
        $data = ['id' => 1, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(1, $object->id);
        self::assertSame('Test', $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaProperties(): void
    {
        $target = new class () {
            public int $id = 0;
            public string $name = '';
        };
        $data = ['id' => 1, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(1, $object->id);
        self::assertSame('Test', $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateWithSnakeCaseToConstructor(): void
    {
        $target = new class (0, '') {
            public function __construct(
                public readonly int $userId,
                public readonly string $fullName,
            ) {
            }
        };
        $data = ['user_id' => 42, 'full_name' => 'John Doe'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(42, $object->userId);
        self::assertSame('John Doe', $object->fullName);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateWithDefaultValues(): void
    {
        $target = new class (0) {
            public function __construct(
                public readonly int $id,
                public readonly string $name = 'default',
            ) {
            }
        };
        $data = ['id' => 1];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(1, $object->id);
        self::assertSame('default', $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateWithNullableParameter(): void
    {
        $target = new class (0) {
            public function __construct(
                public readonly int $id,
                public readonly ?string $name = null,
            ) {
            }
        };
        $data = ['id' => 1];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(1, $object->id);
        self::assertNull($object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testThrowsExceptionForMissingRequiredParameter(): void
    {
        $target = new class (0, '') {
            public function __construct(
                public readonly int $id,
                public readonly string $name,
            ) {
            }
        };
        $this->expectException(\SqlFixture\Hydrator\Exception\MissingConstructorArgumentException::class);
        (new ReflectionHydrator())->hydrate(['name' => 'Test'], $target::class);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testThrowsExceptionForNonExistentClass(): void
    {
        $hydrator = new ReflectionHydrator();
        self::expectException(\SqlFixture\Hydrator\Exception\ClassNotFoundException::class);
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
        $target = new class (0, '') {
            public function __construct(
                public readonly int $id,
                public readonly string $name,
            ) {
            }
        };
        $data = ['id' => '42', 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

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
        $target = new class (0.0) {
            public function __construct(
                public readonly float $amount,
            ) {
            }
        };
        $data = ['amount' => '99.99'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

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
        $target = new class (false) {
            public function __construct(
                public readonly bool $active,
            ) {
            }
        };
        $data = ['active' => 1];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

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
        $target = new class (false) {
            public function __construct(
                public readonly bool $active,
            ) {
            }
        };
        $data = ['active' => 0];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertFalse($object->active);
        self::assertIsBool($object->active);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsArrayValue(): void
    {
        $target = new class ([]) {
            /**
             * @param list<string> $items
             */
            public function __construct(
                public readonly array $items,
            ) {
            }
        };
        $data = ['items' => '["a","b","c"]'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(['a', 'b', 'c'], $object->items);
        self::assertIsArray($object->items);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsArrayFromNonJsonString(): void
    {
        $target = new class ([]) {
            /**
             * @param list<string> $items
             */
            public function __construct(
                public readonly array $items,
            ) {
            }
        };
        $data = ['items' => 'single_value'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(['single_value'], $object->items);
        self::assertIsArray($object->items);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsStringValue(): void
    {
        $target = new class ('') {
            public function __construct(
                public readonly string $value,
            ) {
            }
        };
        $data = ['value' => 123];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

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
        $target = new class () {
            public function __construct(
                public string $userName = '',
            ) {
            }
        };
        $data = ['user_name' => 'John'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame('John', $object->userName);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateIgnoresExtraData(): void
    {
        $target = new class (0, '') {
            public function __construct(
                public readonly int $id,
                public readonly string $name,
            ) {
            }
        };
        $data = ['id' => 1, 'name' => 'Test', 'extra' => 'ignored'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(1, $object->id);
        self::assertSame('Test', $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateWithMixedType(): void
    {
        $target = new class (null) {
            /**
             * @param array{nested: string}|null $value
             */
            public function __construct(
                public readonly mixed $value,
            ) {
            }
        };
        $data = ['value' => ['nested' => 'data']];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(['nested' => 'data'], $object->value);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaPropertiesWithCasting(): void
    {
        $target = new class () {
            public int $id = 0;
            public string $name = '';
            public float $amount = 0.0;
            public bool $active = false;
        };
        $data = ['id' => '42', 'name' => 123, 'amount' => '9.5', 'active' => 1];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

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
        $target = new class () {
            public int $id = 0;
            public string $name = '';
            public float $amount = 0.0;
            public bool $active = false;
        };
        $data = ['unknown_key' => 'value'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(0, $object->id);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaPropertiesSkipsUnknownAndContinues(): void
    {
        $target = new class () {
            public int $id = 0;
            public string $name = '';
            public float $amount = 0.0;
            public bool $active = false;
        };
        $data = ['unknown_key' => 'value', 'id' => 42, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(42, $object->id);
        self::assertSame('Test', $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaPropertiesWithSnakeCase(): void
    {
        $target = new class () {
            public string $userName = '';
        };
        $data = ['user_name' => 'John'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame('John', $object->userName);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaPropertiesDirectKey(): void
    {
        $target = new class () {
            public string $userName = '';
        };
        $data = ['userName' => 'Direct'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame('Direct', $object->userName);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydrateViaConstructorWithEmptyConstructor(): void
    {
        $target = new class () {
            public int $id = 0;
            public string $name = '';

            public function __construct()
            {
            }
        };
        $data = ['id' => 5, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(5, $object->id);
        self::assertSame('Test', $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastNullReturnsNull(): void
    {
        $target = new class (0) {
            public function __construct(
                public readonly int $id,
                public readonly ?string $name = null,
            ) {
            }
        };
        $data = ['id' => 1, 'name' => null];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertNull($object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastArrayFromAlreadyArray(): void
    {
        $target = new class ([]) {
            /**
             * @param list<string> $items
             */
            public function __construct(
                public readonly array $items,
            ) {
            }
        };
        $data = ['items' => ['existing', 'array']];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(['existing', 'array'], $object->items);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydratePreservesStringInMixedIdProperty(): void
    {
        $target = new class () {
            /**
             * @var string|null
             */
            public mixed $id = null;
        };
        $data = ['id' => 'not_a_number'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame('not_a_number', $object->id);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydratePreservesStringInMixedAmountProperty(): void
    {
        $target = new class () {
            /**
             * @var string|null
             */
            public mixed $amount = null;
        };
        $data = ['amount' => 'not_a_number'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame('not_a_number', $object->amount);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testHydratePreservesArrayInMixedNameProperty(): void
    {
        $target = new class () {
            /**
             * @var list<string>|null
             */
            public mixed $name = null;
        };
        $data = ['name' => ['array_value']];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(['array_value'], $object->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastIntToArrayViaCastValue(): void
    {
        $target = new class ([]) {
            /**
             * @param list<int> $items
             */
            public function __construct(
                public readonly array $items,
            ) {
            }
        };
        $data = ['items' => 42];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame([42], $object->items);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastBoolToArrayViaCastValue(): void
    {
        $target = new class ([]) {
            /**
             * @param list<bool> $items
             */
            public function __construct(
                public readonly array $items,
            ) {
            }
        };
        $data = ['items' => true];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame([true], $object->items);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsIntValueFromNumericString(): void
    {
        $target = new class (0, '') {
            public function __construct(
                public readonly int $id,
                public readonly string $name,
            ) {
            }
        };
        $data = ['id' => '123', 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(123, $object->id);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsFloatValueFromNumericString(): void
    {
        $target = new class (0.0) {
            public function __construct(
                public readonly float $amount,
            ) {
            }
        };
        $data = ['amount' => '3.14'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(3.14, $object->amount);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsStringFromIntValue(): void
    {
        $target = new class ('') {
            public function __construct(
                public readonly string $value,
            ) {
            }
        };
        $data = ['value' => 42];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame('42', $object->value);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsBoolFromTruthyValue(): void
    {
        $target = new class (false) {
            public function __construct(
                public readonly bool $active,
            ) {
            }
        };
        $data = ['active' => 'yes'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertTrue($object->active);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastsBoolFromFalsyValue(): void
    {
        $target = new class (false) {
            public function __construct(
                public readonly bool $active,
            ) {
            }
        };
        $data = ['active' => ''];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertFalse($object->active);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastIntFromFloat(): void
    {
        $target = new class (0, '') {
            public function __construct(
                public readonly int $id,
                public readonly string $name,
            ) {
            }
        };
        $data = ['id' => 7.9, 'name' => 'Test'];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(7, $object->id);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastFloatFromInt(): void
    {
        $target = new class (0.0) {
            public function __construct(
                public readonly float $amount,
            ) {
            }
        };
        $data = ['amount' => 10];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame(10.0, $object->amount);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastStringFromBool(): void
    {
        $target = new class ('') {
            public function __construct(
                public readonly string $value,
            ) {
            }
        };
        $data = ['value' => true];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame('1', $object->value);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testCastStringFromFloat(): void
    {
        $target = new class ('') {
            public function __construct(
                public readonly string $value,
            ) {
            }
        };
        $data = ['value' => 3.14];
        $object = (new ReflectionHydrator())->hydrate($data, $target::class);

        self::assertSame('3.14', $object->value);
    }
}
