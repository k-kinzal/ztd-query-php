<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\RandomStringGenerator;

#[CoversClass(RandomStringGenerator::class)]
final class RandomStringGeneratorTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        gc_collect_cycles();
    }

    public function testRawIdentifierStartsWithSafeCanonicalPrefix(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $results = array_map(static fn (): string => $rsg->rawIdentifier(), range(1, 50));
        self::assertCount(50, array_filter($results, static fn (string $s): bool => preg_match('/^_[a-z0-9_]*$/', $s) === 1));
    }

    public function testRawIdentifierLengthRange(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $lengths = array_map(static fn (): int => strlen($rsg->rawIdentifier()), range(1, 200));
        self::assertGreaterThanOrEqual(1, min($lengths));
        self::assertLessThanOrEqual(12, max($lengths));
    }

    public function testCanonicalIdentifierUsesStableSafePrefix(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);

        self::assertSame('_i0', $rsg->canonicalIdentifier(0));
        self::assertSame('_i1', $rsg->canonicalIdentifier(1));
        self::assertSame('_iz', $rsg->canonicalIdentifier(35));
        self::assertSame('_i10', $rsg->canonicalIdentifier(36));
    }

    public function testCanonicalIdentifierRemainsUniqueAcrossOrdinals(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);
        $values = array_map(static fn (int $ordinal): string => $rsg->canonicalIdentifier($ordinal), range(0, 128));

        self::assertCount(129, array_unique($values));
        self::assertCount(129, array_filter($values, static fn (string $value): bool => preg_match('/^_i[a-z0-9]+$/', $value) === 1));
    }

    public function testMixedAlnumStringCharacterSet(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $results = array_map(static fn (): string => $rsg->mixedAlnumString(), range(1, 50));
        self::assertCount(50, array_filter($results, static fn (string $s): bool => preg_match('/^[a-zA-Z0-9_]+$/', $s) === 1));
    }

    public function testMixedAlnumStringLengthRange(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $lengths = array_map(static fn (): int => strlen($rsg->mixedAlnumString()), range(1, 200));
        self::assertGreaterThanOrEqual(1, min($lengths));
        self::assertLessThanOrEqual(24, max($lengths));
    }

    public function testIntegerStringFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $results = array_map(static fn (): string => $rsg->integerString(), range(1, 50));
        self::assertCount(50, array_filter($results, static fn (string $s): bool => preg_match('/^[1-9][0-9]*$/', $s) === 1));
    }

    public function testIntegerStringDigitCountRange(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $lengths = array_map(static fn (): int => strlen($rsg->integerString()), range(1, 200));
        self::assertGreaterThanOrEqual(1, min($lengths));
        self::assertLessThanOrEqual(10, max($lengths));
    }

    public function testHexStringFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $results = array_map(static fn (): string => $rsg->hexString(), range(1, 50));
        self::assertCount(50, array_filter($results, static fn (string $s): bool => preg_match('/^[0-9a-f]+$/', $s) === 1));
    }

    public function testHexStringLengthRange(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $lengths = array_map(static fn (): int => strlen($rsg->hexString()), range(1, 200));
        self::assertGreaterThanOrEqual(1, min($lengths));
        self::assertLessThanOrEqual(16, max($lengths));
    }

    public function testBinaryStringFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $results = array_map(static fn (): string => $rsg->binaryString(), range(1, 50));
        self::assertCount(50, array_filter($results, static fn (string $s): bool => preg_match('/^[01]+$/', $s) === 1));
    }

    public function testBinaryStringLengthRange(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $lengths = array_map(static fn (): int => strlen($rsg->binaryString()), range(1, 200));
        self::assertGreaterThanOrEqual(1, min($lengths));
        self::assertLessThanOrEqual(32, max($lengths));
    }

    public function testDecimalStringFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $results = array_map(static fn (): string => $rsg->decimalString(), range(1, 50));
        self::assertCount(50, array_filter($results, static fn (string $s): bool => preg_match('/^\d+\.\d{2,}$/', $s) === 1));
    }

    public function testHostnameStringFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $results = array_map(static fn (): string => $rsg->hostnameString(), range(1, 50));
        self::assertCount(50, array_filter($results, static fn (string $s): bool => preg_match('/^[a-z0-9]+(\.[a-z0-9]+)*$/', $s) === 1));
    }

    public function testHostnameStringStartsWithLetterInEachSegment(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $results = array_map(static fn (): string => $rsg->hostnameString(), range(1, 50));

        self::assertCount(50, array_filter($results, static function (string $hostname): bool {
            foreach (explode('.', $hostname) as $segment) {
                if (preg_match('/^[a-z][a-z0-9]*$/', $segment) !== 1) {
                    return false;
                }
            }

            return true;
        }));
    }

    public function testUnsignedBigIntStringFormat(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $results = array_map(static fn (): string => $rsg->unsignedBigIntString(), range(1, 50));
        self::assertCount(50, array_filter($results, static fn (string $s): bool => preg_match('/^(0|[1-9][0-9]*)$/', $s) === 1));
    }

    public function testUnsignedBigIntStringLengthRange(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $lengths = array_map(static fn (): int => strlen($rsg->unsignedBigIntString()), range(1, 200));
        self::assertGreaterThanOrEqual(1, min($lengths));
        self::assertLessThanOrEqual(20, max($lengths));
    }

    public function testRawIdentifierDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);

        $faker->seed(42);
        $a = $rsg->rawIdentifier();
        $faker->seed(42);
        $b = $rsg->rawIdentifier(1, 12);
        self::assertSame($a, $b);
    }

    public function testMixedAlnumStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);

        $faker->seed(42);
        $a = $rsg->mixedAlnumString();
        $faker->seed(42);
        $b = $rsg->mixedAlnumString(1, 24);
        self::assertSame($a, $b);
    }

    public function testIntegerStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);

        $faker->seed(42);
        $a = $rsg->integerString();
        $faker->seed(42);
        $b = $rsg->integerString(1, 9999999999);
        self::assertSame($a, $b);
    }

    public function testHexStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);

        $faker->seed(42);
        $a = $rsg->hexString();
        $faker->seed(42);
        $b = $rsg->hexString(1, 16);
        self::assertSame($a, $b);
    }

    public function testBinaryStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);

        $faker->seed(42);
        $a = $rsg->binaryString();
        $faker->seed(42);
        $b = $rsg->binaryString(1, 32);
        self::assertSame($a, $b);
    }

    public function testDecimalStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);

        $faker->seed(42);
        $a = $rsg->decimalString();
        $faker->seed(42);
        $b = $rsg->decimalString(13, 6);
        self::assertSame($a, $b);
    }

    public function testHostnameStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);

        $faker->seed(42);
        $a = $rsg->hostnameString();
        $faker->seed(42);
        $b = $rsg->hostnameString(1, 3, 1, 12);
        self::assertSame($a, $b);
    }

    public function testUnsignedBigIntStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);

        $faker->seed(42);
        $a = $rsg->unsignedBigIntString();
        $faker->seed(42);
        $b = $rsg->unsignedBigIntString(1, 20);
        self::assertSame($a, $b);
    }

    public function testLongIntStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);

        $faker->seed(42);
        $a = $rsg->longIntString();
        $faker->seed(42);
        $b = $rsg->longIntString(0, 2147483647);
        self::assertSame($a, $b);
    }

    public function testFloatStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);

        $faker->seed(42);
        $a = $rsg->floatString('1.0');
        $faker->seed(42);
        $b = $rsg->floatString('1.0', -20, 20);
        self::assertSame($a, $b);
    }

    public function testParameterIndexDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $rsg = new RandomStringGenerator($faker);

        $faker->seed(42);
        $a = $rsg->parameterIndex();
        $faker->seed(42);
        $b = $rsg->parameterIndex(1, 10);
        self::assertSame($a, $b);
    }

    public function testRawIdentifierCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $lengths = array_map(static fn (): int => strlen($rsg->rawIdentifier(3, 5)), range(1, 100));
        self::assertGreaterThanOrEqual(3, min($lengths));
        self::assertLessThanOrEqual(5, max($lengths));
    }

    public function testMixedAlnumStringCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $lengths = array_map(static fn (): int => strlen($rsg->mixedAlnumString(10, 15)), range(1, 100));
        self::assertGreaterThanOrEqual(10, min($lengths));
        self::assertLessThanOrEqual(15, max($lengths));
    }

    public function testIntegerStringCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $values = array_map(static fn (): int => (int) $rsg->integerString(100, 200), range(1, 100));
        self::assertGreaterThanOrEqual(100, min($values));
        self::assertLessThanOrEqual(200, max($values));
    }

    public function testHexStringCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $lengths = array_map(static fn (): int => strlen($rsg->hexString(4, 8)), range(1, 100));
        self::assertGreaterThanOrEqual(4, min($lengths));
        self::assertLessThanOrEqual(8, max($lengths));
    }

    public function testBinaryStringCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $lengths = array_map(static fn (): int => strlen($rsg->binaryString(8, 16)), range(1, 100));
        self::assertGreaterThanOrEqual(8, min($lengths));
        self::assertLessThanOrEqual(16, max($lengths));
    }

    public function testDecimalStringCustomPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $result = $rsg->decimalString(5, 2);
        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testDecimalStringWithEqualPrecisionAndScale(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $result = $rsg->decimalString(2, 2);
        self::assertMatchesRegularExpression('/^\d\.\d{2}$/', $result);
    }

    public function testDecimalStringFracDigitsMatchScale(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $result = $rsg->decimalString(8, 4);
        $parts = explode('.', $result);
        self::assertSame(4, strlen($parts[1]));
    }

    public function testDecimalStringWithScaleOneHasTwoFracDigits(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $result = $rsg->decimalString(5, 1);
        $parts = explode('.', $result);
        self::assertSame(2, strlen($parts[1]));
    }

    public function testDecimalStringPrecisionLimitsIntPartDigits(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $result = $rsg->decimalString(4, 2);
        $parts = explode('.', $result);
        $trimmed = ltrim($parts[0], '0');
        $intPartDigits = $trimmed === '' ? 1 : strlen($trimmed);
        self::assertLessThanOrEqual(2, $intPartDigits);
    }

    public function testUnsignedBigIntStringCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $lengths = array_map(static fn (): int => strlen($rsg->unsignedBigIntString(5, 10)), range(1, 100));
        self::assertGreaterThanOrEqual(1, min($lengths));
        self::assertLessThanOrEqual(10, max($lengths));
    }

    public function testLongIntStringCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $values = array_map(static fn (): int => (int) $rsg->longIntString(50, 100), range(1, 100));
        self::assertGreaterThanOrEqual(50, min($values));
        self::assertLessThanOrEqual(100, max($values));
    }

    public function testFloatStringCustomExponent(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $result = $rsg->floatString('1.5', -5, 5);
        self::assertMatchesRegularExpression('/^1\.5e-?\d+$/', $result);
    }

    public function testParameterIndexCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $values = array_map(static fn (): int => (int) $rsg->parameterIndex(1, 3), range(1, 100));
        self::assertGreaterThanOrEqual(1, min($values));
        self::assertLessThanOrEqual(3, max($values));
    }

    public function testHostnameStringCustomParts(): void
    {
        $faker = Factory::create();
        $faker->seed(42);
        $rsg = new RandomStringGenerator($faker);
        $result = $rsg->hostnameString(2, 2, 3, 3);
        self::assertMatchesRegularExpression('/^[a-z0-9]{3}\.[a-z0-9]{3}$/', $result);
    }
}
