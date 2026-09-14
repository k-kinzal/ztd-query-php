<?php

declare(strict_types=1);

namespace Tests\Unit\Hydrator\Reflection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Hydrator\Reflection\ConversionTarget;
use SqlFixture\Hydrator\Reflection\ValueConversion;

#[CoversClass(ValueConversion::class)]
#[UsesClass(ConversionTarget::class)]
final class ValueConversionTest extends TestCase
{
    /**
     * @param list<int|string>|int|float|bool|string|null $value
     * @param list<int|string>|int|float|bool|string|null $expected
     */
    #[DataProvider('providerConversions')]
    public function testCastValuePreservesOrConvertsTheDatabaseValue(
        array|int|float|bool|string|null $value,
        ?ConversionTarget $type,
        array|int|float|bool|string|null $expected,
    ): void {
        self::assertSame($expected, (new ValueConversion())->castValue($value, $type));
    }

    /**
     * @return iterable<string, array{list<int|string>|int|float|bool|string|null, ?ConversionTarget, list<int|string>|int|float|bool|string|null}>
     */
    public static function providerConversions(): iterable
    {
        yield 'numeric string to integer' => ['42', ConversionTarget::Integer, 42];
        yield 'numeric float to integer' => [42.9, ConversionTarget::Integer, 42];
        yield 'nonnumeric integer input is preserved' => ['pending', ConversionTarget::Integer, 'pending'];
        yield 'numeric string to float' => ['12.5', ConversionTarget::Float, 12.5];
        yield 'integer to float' => [12, ConversionTarget::Float, 12.0];
        yield 'nonnumeric float input is preserved' => ['pending', ConversionTarget::Float, 'pending'];
        yield 'integer to string' => [42, ConversionTarget::String, '42'];
        yield 'boolean to string' => [false, ConversionTarget::String, ''];
        yield 'nonscalar string input is preserved' => [[1, 2], ConversionTarget::String, [1, 2]];
        yield 'zero to boolean' => [0, ConversionTarget::Boolean, false];
        yield 'one to boolean' => [1, ConversionTarget::Boolean, true];
        yield 'null stays null' => [null, ConversionTarget::Boolean, null];
        yield 'JSON array' => ['[1,2]', ConversionTarget::Array, [1, 2]];
        yield 'JSON null becomes an element' => ['null', ConversionTarget::Array, ['null']];
        yield 'plain text becomes an element' => ['plain', ConversionTarget::Array, ['plain']];
        yield 'scalar becomes an array' => [42, ConversionTarget::Array, [42]];
        yield 'existing array is preserved' => [[1, 2], ConversionTarget::Array, [1, 2]];
        yield 'unconverted type preserves numeric strings' => ['42', null, '42'];
    }
}
