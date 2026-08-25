<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionNamedType;
use ReflectionProperty;
use SqlFixture\Hydrator\DeclaredTypeCast;
use Tests\Fixture\Hydrator\TestEntityViaProperties;

#[CoversClass(DeclaredTypeCast::class)]
final class DeclaredTypeCastTest extends TestCase
{
    #[DataProvider('providerDeclaredType')]
    public function testOfReadsAWireValueAsTheTypeItIsAssignedTo(
        string $property,
        mixed $written,
        mixed $expected,
    ): void {
        $type = (new ReflectionProperty(TestEntityViaProperties::class, $property))->getType();

        self::assertSame($expected, (new DeclaredTypeCast())->of($written, $type));
    }

    /**
     * @return iterable<string, array{string, mixed, mixed}>
     */
    public static function providerDeclaredType(): iterable
    {
        yield 'int from a driver string' => ['id', '42', 42];
        yield 'float from a driver string' => ['amount', '1.5', 1.5];
        yield 'string from a driver integer' => ['name', 7, '7'];
        yield 'bool from a driver string' => ['active', '1', true];
    }

    public function testOfLeavesNullAlone(): void
    {
        $type = (new ReflectionProperty(TestEntityViaProperties::class, 'id'))->getType();

        self::assertNull((new DeclaredTypeCast())->of(null, $type));
    }

    public function testOfLeavesAValueAloneWhenNoTypeWasDeclared(): void
    {
        self::assertSame('42', (new DeclaredTypeCast())->of('42', null));
    }

    public function testOfLeavesAValueTheDeclaredTypeCannotBeReadFrom(): void
    {
        $type = (new ReflectionProperty(TestEntityViaProperties::class, 'id'))->getType();

        self::assertSame('not a number', (new DeclaredTypeCast())->of('not a number', $type));
    }

    public function testOfLeavesAValueWhoseDeclaredTypeNamesNoSingleType(): void
    {
        self::assertSame('42', (new DeclaredTypeCast())->of('42', self::createStub(ReflectionNamedType::class)));
    }
}
