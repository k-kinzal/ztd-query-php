<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Type\TypeShape;

#[CoversClass(TypeShape::class)]
final class TypeShapeTest extends TestCase
{
    public function testUnknownAdmitsEverything(): void
    {
        self::assertSame(['mixed'], TypeShape::unknown()->names);
    }

    public function testOfSortsAndDeduplicatesAlternatives(): void
    {
        self::assertSame(['int', 'null', 'string'], TypeShape::of(['string', 'int', 'string', 'null'])->names);
    }

    public function testOfCollapsesToMixedWhenAnyAlternativeIsMixed(): void
    {
        self::assertSame(['mixed'], TypeShape::of(['string', 'mixed'])->names);
    }

    public function testOfFallsBackToUnknownForAnEmptyList(): void
    {
        self::assertSame(['mixed'], TypeShape::of([])->names);
    }

    #[DataProvider('providerNormalize')]
    public function testNormalize(string $written, string $expected): void
    {
        self::assertSame($expected, TypeShape::normalize($written));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerNormalize(): array
    {
        return [
            ['boolean', 'bool'],
            ['integer', 'int'],
            ['double', 'float'],
            ['false', 'bool'],
            ['true', 'bool'],
            ['STRING', 'string'],
            ['\\App\\Status', 'App\\Status'],
            [' App\\Status ', 'App\\Status'],
        ];
    }

    public function testIsUnknownOnlyForMixed(): void
    {
        self::assertTrue(TypeShape::unknown()->isUnknown());
        self::assertFalse(TypeShape::of(['string'])->isUnknown());
    }

    public function testIsNullableWhenNullIsAnAlternative(): void
    {
        self::assertTrue(TypeShape::of(['string', 'null'])->isNullable());
        self::assertFalse(TypeShape::of(['string'])->isNullable());
    }

    public function testClassNamesKeepsOnlyDeclaredClasses(): void
    {
        self::assertSame(['App\\Status'], TypeShape::of(['null', 'App\\Status'])->classNames());
        self::assertSame([], TypeShape::of(['int', 'string'])->classNames());
    }

    public function testSoleClassNameIgnoresNullButNotOtherAlternatives(): void
    {
        self::assertSame('App\\Status', TypeShape::of(['App\\Status', 'null'])->soleClassName());
        self::assertNull(TypeShape::of(['App\\Status', 'int'])->soleClassName());
        self::assertNull(TypeShape::of(['App\\Status', 'App\\Other'])->soleClassName());
    }

    public function testUnionCombinesAlternatives(): void
    {
        self::assertSame(['int', 'string'], TypeShape::of(['string'])->union(TypeShape::of(['int']))->names);
    }

    public function testWithoutNullDropsNull(): void
    {
        self::assertSame(['string'], TypeShape::of(['string', 'null'])->withoutNull()->names);
    }

    public function testWithoutNullFallsBackToUnknownWhenNothingIsLeft(): void
    {
        self::assertSame(['mixed'], TypeShape::of(['null'])->withoutNull()->names);
    }

    public function testDisplayWritesAlternativesTheWayPhpDoes(): void
    {
        self::assertSame('int|string', TypeShape::of(['string', 'int'])->display());
    }
}
