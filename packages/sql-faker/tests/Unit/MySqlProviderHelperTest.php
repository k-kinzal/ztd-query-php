<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\MySqlProvider;

#[CoversClass(MySqlProvider::class)]
#[CoversClass(RandomStringGenerator::class)]
final class MySqlProviderHelperTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        gc_collect_cycles();
    }

    public function testQuotedIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->quotedIdentifier();

        self::assertMatchesRegularExpression('/^`[a-z_][a-z0-9_]*`$/', $result);
    }

    public function testStringLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->stringLiteral();

        self::assertMatchesRegularExpression("/^'[a-zA-Z0-9_]{1,32}'$/", $result);
    }

    public function testStringLiteralLengthRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->stringLiteral();
        $content = substr($result, 1, -1);

        self::assertGreaterThanOrEqual(1, strlen($content));
        self::assertLessThanOrEqual(32, strlen($content));
    }

    public function testNationalStringLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->nationalStringLiteral();

        self::assertMatchesRegularExpression("/^N'[a-zA-Z0-9_]{1,32}'$/", $result);
    }

    public function testDollarQuotedString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->dollarQuotedString();

        self::assertMatchesRegularExpression('/^\$\$[a-zA-Z0-9_]{1,32}\$\$$/', $result);
    }

    public function testIntegerLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->integerLiteral();

        self::assertMatchesRegularExpression('/^[1-9]\d*$/', $result);
    }

    public function testLongIntegerLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->longIntegerLiteral();

        self::assertMatchesRegularExpression('/^\d+$/', $result);
        self::assertGreaterThanOrEqual(0, (int) $result);
        self::assertLessThanOrEqual(2147483647, (int) $result);
    }

    public function testUnsignedBigIntLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->unsignedBigIntLiteral();

        self::assertMatchesRegularExpression('/^\d+$/', $result);
    }

    public function testDecimalLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->decimalLiteral();

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testFloatLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->floatLiteral();

        self::assertMatchesRegularExpression('/^\d+\.\d+e-?\d+$/', $result);
    }

    public function testHexLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->hexLiteral();

        self::assertMatchesRegularExpression('/^0x[0-9a-f]{1,16}$/', $result);
    }

    public function testBinaryLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->binaryLiteral();

        self::assertMatchesRegularExpression('/^0b[01]{1,64}$/', $result);
    }

    public function testHostname(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->hostname();

        self::assertMatchesRegularExpression('/^[a-z0-9]+(\.[a-z0-9]+)*$/', $result);
    }

    public function testQuotedIdentifierDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->quotedIdentifier();
        $faker->seed(42);

        self::assertSame($result, $provider->quotedIdentifier(1, 64));
    }

    public function testStringLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->stringLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->stringLiteral(1, 32));
    }

    public function testNationalStringLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->nationalStringLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->nationalStringLiteral(1, 32));
    }

    public function testDollarQuotedStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->dollarQuotedString();
        $faker->seed(42);

        self::assertSame($result, $provider->dollarQuotedString(1, 32));
    }

    public function testIntegerLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->integerLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->integerLiteral(1, 2147483647));
    }

    public function testLongIntegerLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->longIntegerLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->longIntegerLiteral(0, 2147483647));
    }

    public function testUnsignedBigIntLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->unsignedBigIntLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->unsignedBigIntLiteral(1, 20));
    }

    public function testDecimalLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->decimalLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->decimalLiteral(10, 2));
    }

    public function testFloatLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->floatLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->floatLiteral(10, 2, -38, 38));
    }

    public function testHexLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->hexLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->hexLiteral(1, 16));
    }

    public function testBinaryLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->binaryLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->binaryLiteral(1, 64));
    }

    public function testHostnameDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);
        $faker->seed(42);
        $result = $provider->hostname();
        $faker->seed(42);

        self::assertSame($result, $provider->hostname(1, 1, 16));
    }

    public function testQuotedIdentifierCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->quotedIdentifier(5, 10);

        self::assertMatchesRegularExpression('/^`[a-z_][a-z0-9_]{4,9}`$/', $result);
    }

    public function testStringLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->stringLiteral(3, 8);
        $content = substr($result, 1, -1);

        self::assertGreaterThanOrEqual(3, strlen($content));
        self::assertLessThanOrEqual(8, strlen($content));
    }

    public function testNationalStringLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->nationalStringLiteral(2, 5);
        $content = substr($result, 2, -1);

        self::assertGreaterThanOrEqual(2, strlen($content));
        self::assertLessThanOrEqual(5, strlen($content));
    }

    public function testDollarQuotedStringCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->dollarQuotedString(2, 6);
        $content = substr($result, 2, -2);

        self::assertGreaterThanOrEqual(2, strlen($content));
        self::assertLessThanOrEqual(6, strlen($content));
    }

    public function testIntegerLiteralCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->integerLiteral(100, 500);

        self::assertGreaterThanOrEqual(100, (int) $result);
        self::assertLessThanOrEqual(500, (int) $result);
    }

    public function testLongIntegerLiteralCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->longIntegerLiteral(10, 100);

        self::assertGreaterThanOrEqual(10, (int) $result);
        self::assertLessThanOrEqual(100, (int) $result);
    }

    public function testDecimalLiteralCustomPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->decimalLiteral(5, 2);

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testFloatLiteralCustomParams(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->floatLiteral(5, 2, -10, 10);

        self::assertMatchesRegularExpression('/^\d+\.\d+e-?\d+$/', $result);
    }

    public function testHexLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->hexLiteral(4, 8);

        self::assertMatchesRegularExpression('/^0x[0-9a-f]{4,8}$/', $result);
    }

    public function testBinaryLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->binaryLiteral(8, 16);

        self::assertMatchesRegularExpression('/^0b[01]{8,16}$/', $result);
    }

    public function testHostnameCustomParams(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->hostname(2, 3, 5);

        self::assertMatchesRegularExpression('/^[a-z][a-z0-9]{0,4}(\.[a-z][a-z0-9]{0,4}){1,2}$/', $result);
    }

    public function testHostnameSinglePartCustomParams(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->hostname(1, 1, 12);

        self::assertStringNotContainsString('.', $result);
        self::assertMatchesRegularExpression('/^[a-z][a-z0-9]{0,11}$/', $result);
    }

    public function testFilterWildcardPattern(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->filterWildcardPattern();

        self::assertMatchesRegularExpression("/^'[a-z][a-z0-9]{0,11}\\.[a-z][a-z0-9]{0,11}'$/", $result);
    }

    public function testFilterWildcardPatternCustomMaxPartLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->filterWildcardPattern(13);

        self::assertMatchesRegularExpression("/^'[a-z][a-z0-9]{0,12}\\.[a-z][a-z0-9]{0,12}'$/", $result);
    }

    public function testResetMasterIndex(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new MySqlProvider($faker);

        $result = $provider->resetMasterIndex();

        self::assertMatchesRegularExpression('/^\d+$/', $result);
        self::assertGreaterThanOrEqual(1, (int) $result);
        self::assertLessThanOrEqual(2_000_000_000, (int) $result);
    }

    #[DataProvider('providerDeterministicHelperGeneration')]
    /**
     * @param \Closure(MySqlProvider): string $generate
     */
    public function testDeterministicHelperOutputIsReproducible(\Closure $generate): void
    {
        $faker = Factory::create();
        $provider = new MySqlProvider($faker);

        $faker->seed(0);
        $first = $generate($provider);

        $faker->seed(0);
        $second = $generate($provider);

        self::assertSame($first, $second);
    }

    /**
     * @return iterable<string, array{\Closure(MySqlProvider): string}>
     */
    public static function providerDeterministicHelperGeneration(): iterable
    {
        yield 'default string literal' => [static fn (MySqlProvider $provider): string => $provider->stringLiteral()];
        yield 'custom string literal' => [static fn (MySqlProvider $provider): string => $provider->stringLiteral(3, 8)];
        yield 'default national string literal' => [static fn (MySqlProvider $provider): string => $provider->nationalStringLiteral()];
        yield 'custom national string literal' => [static fn (MySqlProvider $provider): string => $provider->nationalStringLiteral(2, 5)];
        yield 'default dollar quoted string' => [static fn (MySqlProvider $provider): string => $provider->dollarQuotedString()];
        yield 'custom dollar quoted string' => [static fn (MySqlProvider $provider): string => $provider->dollarQuotedString(2, 6)];
        yield 'default hostname' => [static fn (MySqlProvider $provider): string => $provider->hostname()];
        yield 'custom hostname' => [static fn (MySqlProvider $provider): string => $provider->hostname(2, 3, 5)];
        yield 'custom single-part hostname' => [static fn (MySqlProvider $provider): string => $provider->hostname(1, 1, 12)];
        yield 'default filter wildcard pattern' => [static fn (MySqlProvider $provider): string => $provider->filterWildcardPattern()];
        yield 'custom filter wildcard pattern' => [static fn (MySqlProvider $provider): string => $provider->filterWildcardPattern(13)];
        yield 'reset master index' => [static fn (MySqlProvider $provider): string => $provider->resetMasterIndex()];
    }
}
