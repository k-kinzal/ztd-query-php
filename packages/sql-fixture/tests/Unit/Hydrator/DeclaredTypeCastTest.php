<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\DeclaredTypeCast;

#[CoversClass(DeclaredTypeCast::class)]
final class DeclaredTypeCastTest extends TestCase
{
    #[DataProvider('providerWireValue')]
    public function testAsTypeReadsAWireValueAsTheTypeItIsAssignedTo(
        string $typeName,
        mixed $written,
        mixed $expected,
    ): void {
        self::assertSame($expected, (new DeclaredTypeCast())->asType($written, $typeName));
    }

    /**
     * @return iterable<string, array{string, mixed, mixed}>
     */
    public static function providerWireValue(): iterable
    {
        yield 'int from a driver string' => ['int', '42', 42];
        yield 'float from a driver string' => ['float', '1.5', 1.5];
        yield 'string from a driver integer' => ['string', 7, '7'];
        yield 'bool from a driver string' => ['bool', '1', true];
        yield 'bool from an empty string' => ['bool', '', false];
        yield 'array from json' => ['array', '{"a":1}', ['a' => 1]];
    }

    public function testAsTypeWrapsTextThatIsNotJsonRatherThanLosingIt(): void
    {
        self::assertSame(['plain'], (new DeclaredTypeCast())->asType('plain', 'array'));
    }

    public function testAsTypeLeavesNullAlone(): void
    {
        self::assertNull((new DeclaredTypeCast())->asType(null, 'int'));
    }

    #[DataProvider('providerUnreadableValue')]
    public function testAsTypeLeavesAValueTheDeclaredTypeCannotBeReadFrom(string $typeName, mixed $written): void
    {
        self::assertSame($written, (new DeclaredTypeCast())->asType($written, $typeName));
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function providerUnreadableValue(): iterable
    {
        yield 'int from words' => ['int', 'not a number'];
        yield 'float from words' => ['float', 'not a number'];
        yield 'string from an array' => ['string', ['a']];
        yield 'a type nothing is read as' => ['DateTimeImmutable', 'x'];
    }

    public function testOfLeavesAValueAloneWhenNoTypeWasDeclared(): void
    {
        self::assertSame('42', (new DeclaredTypeCast())->of('42', null));
    }
}
