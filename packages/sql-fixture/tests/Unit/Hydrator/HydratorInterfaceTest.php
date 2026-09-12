<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlFixture\Hydrator\HydratorInterface as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\HydrationException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\ReflectionHydrator::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\ConstructorHydration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\PropertyHydration::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\PropertyNames::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Hydrator\Reflection\ValueConversion::class)]
final class HydratorInterfaceTest extends TestCase
{
    /**
     * @throws ReflectionException
     */
    public function testHydrateConvertsDatabaseScalarValues(): void
    {
        $object = (new \SqlFixture\Hydrator\ReflectionHydrator())->hydrate(['id' => '42', 'name' => 'Alice'], \Tests\Fixture\Hydrator\TestEntity::class);
        self::assertSame(42, $object->id);
        self::assertSame('Alice', $object->name);
    }
}
