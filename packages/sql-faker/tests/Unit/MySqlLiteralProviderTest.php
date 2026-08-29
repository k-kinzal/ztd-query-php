<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySqlLiteralProvider;

#[CoversClass(MySqlLiteralProvider::class)]
final class MySqlLiteralProviderTest extends TestCase
{
    public function testRegistersItselfWithTheFakerGenerator(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);

        /**
         * @var list<object> $providers
         */
        $providers = $faker->getProviders();
        self::assertContains($provider, $providers);

        $identifier = $provider->identifier(3);
        self::assertNotSame('', $identifier);
    }

    public function testIdentifier(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->identifier(3);

        self::assertNotSame('', $result);
    }

    public function testQuotedIdentifier(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->quotedIdentifier();

        self::assertMatchesRegularExpression('/^`[a-z_][a-z0-9_]*`$/', $result);
    }

    public function testStringLiteral(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->stringLiteral();

        self::assertMatchesRegularExpression("/^'[a-zA-Z0-9_]{1,255}'$/", $result);
    }

    public function testStringLiteralLengthRange(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $literal = $provider->stringLiteral();
        $content = substr($literal, 1, -1);

        self::assertGreaterThanOrEqual(1, strlen($content));
        self::assertLessThanOrEqual(255, strlen($content));
    }

    public function testNationalStringLiteral(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->nationalStringLiteral();

        self::assertMatchesRegularExpression("/^N'[a-zA-Z0-9_]{1,255}'$/", $result);
    }

    public function testDollarQuotedString(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->dollarQuotedString();

        self::assertMatchesRegularExpression('/^\$\$[a-zA-Z0-9_]{1,255}\$\$$/', $result);
    }

    public function testIntegerLiteral(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->integerLiteral();

        self::assertMatchesRegularExpression('/^[1-9]\d*$/', $result);
    }

    public function testLongIntegerLiteral(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->longIntegerLiteral();

        self::assertMatchesRegularExpression('/^\d+$/', $result);
        self::assertGreaterThanOrEqual(0, (int) $result);
        self::assertLessThanOrEqual(2147483647, (int) $result);
    }

    public function testUnsignedBigIntLiteral(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->unsignedBigIntLiteral();

        self::assertMatchesRegularExpression('/^\d+$/', $result);
    }

    public function testDecimalLiteral(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->decimalLiteral();

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testFloatLiteral(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->floatLiteral();

        self::assertMatchesRegularExpression('/^\d+\.\d+e-?\d+$/', $result);
    }

    public function testHexLiteral(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->hexLiteral();

        self::assertMatchesRegularExpression('/^0x[0-9a-f]{1,16}$/', $result);
    }

    public function testQuotedHexLiteral(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->quotedHexLiteral(4, 4);

        self::assertMatchesRegularExpression("/^X'[0-9a-f]{8}'$/", $result);
    }

    public function testQuotedHexLiteralDefaultsToOneThroughEightBytes(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(5);

        $result = $provider->quotedHexLiteral();
        $faker->seed(5);

        self::assertSame($provider->quotedHexLiteral(1, 8), $result);
        self::assertMatchesRegularExpression("/^X'[0-9a-f]{2,16}'$/", $result);
        self::assertSame(0, (strlen($result) - 3) % 2);
    }

    public function testBinaryLiteral(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->binaryLiteral();

        self::assertMatchesRegularExpression('/^0b[01]{1,64}$/', $result);
    }

    public function testHostname(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->hostname();

        self::assertMatchesRegularExpression('/^[a-z0-9]+(\.[a-z0-9]+)*$/', $result);
    }

    public function testQuotedIdentifierCustomLength(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->quotedIdentifier(5, 10);

        self::assertMatchesRegularExpression('/^`[a-z_][a-z0-9_]{4,9}`$/', $result);
    }

    public function testStringLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->stringLiteral(3, 8);
        $content = substr($result, 1, -1);

        self::assertGreaterThanOrEqual(3, strlen($content));
        self::assertLessThanOrEqual(8, strlen($content));
    }

    public function testNationalStringLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->nationalStringLiteral(2, 5);
        $content = substr($result, 2, -1);

        self::assertGreaterThanOrEqual(2, strlen($content));
        self::assertLessThanOrEqual(5, strlen($content));
    }

    public function testDollarQuotedStringCustomLength(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->dollarQuotedString(2, 6);
        $content = substr($result, 2, -2);

        self::assertGreaterThanOrEqual(2, strlen($content));
        self::assertLessThanOrEqual(6, strlen($content));
    }

    public function testIntegerLiteralCustomRange(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->integerLiteral(100, 500);

        self::assertGreaterThanOrEqual(100, (int) $result);
        self::assertLessThanOrEqual(500, (int) $result);
    }

    public function testLongIntegerLiteralCustomRange(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->longIntegerLiteral(10, 100);

        self::assertGreaterThanOrEqual(10, (int) $result);
        self::assertLessThanOrEqual(100, (int) $result);
    }

    public function testDecimalLiteralCustomPrecision(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->decimalLiteral(5, 2);

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testFloatLiteralCustomParams(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->floatLiteral(5, 2, -10, 10);

        self::assertMatchesRegularExpression('/^\d+\.\d+e-?\d+$/', $result);
    }

    public function testHexLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->hexLiteral(4, 8);

        self::assertMatchesRegularExpression('/^0x[0-9a-f]{4,8}$/', $result);
    }

    public function testBinaryLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->binaryLiteral(8, 16);

        self::assertMatchesRegularExpression('/^0b[01]{8,16}$/', $result);
    }

    public function testHostnameCustomParams(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(12345);

        $result = $provider->hostname(2, 3, 5);

        self::assertMatchesRegularExpression('/^[a-z0-9]+(\.[a-z0-9]+)+$/', $result);
    }

    public function testQuotedIdentifierDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(42);
        $a = $provider->quotedIdentifier();
        $faker->seed(42);
        self::assertSame($a, $provider->quotedIdentifier(1, 64));
    }

    public function testStringLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(42);
        $a = $provider->stringLiteral();
        $faker->seed(42);
        self::assertSame($a, $provider->stringLiteral(1, 255));
    }

    public function testNationalStringLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(42);
        $a = $provider->nationalStringLiteral();
        $faker->seed(42);
        self::assertSame($a, $provider->nationalStringLiteral(1, 255));
    }

    public function testDollarQuotedStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(42);
        $a = $provider->dollarQuotedString();
        $faker->seed(42);
        self::assertSame($a, $provider->dollarQuotedString(1, 255));
    }

    public function testIntegerLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(42);
        $a = $provider->integerLiteral();
        $faker->seed(42);
        self::assertSame($a, $provider->integerLiteral(1, 2147483647));
    }

    public function testLongIntegerLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(42);
        $a = $provider->longIntegerLiteral();
        $faker->seed(42);
        self::assertSame($a, $provider->longIntegerLiteral(0, 2147483647));
    }

    public function testUnsignedBigIntLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(42);
        $a = $provider->unsignedBigIntLiteral();
        $faker->seed(42);
        self::assertSame($a, $provider->unsignedBigIntLiteral(1, 20));
    }

    public function testDecimalLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(42);
        $a = $provider->decimalLiteral();
        $faker->seed(42);
        self::assertSame($a, $provider->decimalLiteral(10, 2));
    }

    public function testFloatLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(42);
        $a = $provider->floatLiteral();
        $faker->seed(42);
        self::assertSame($a, $provider->floatLiteral(10, 2, -38, 38));
    }

    public function testHexLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(42);
        $a = $provider->hexLiteral();
        $faker->seed(42);
        self::assertSame($a, $provider->hexLiteral(1, 16));
    }

    public function testBinaryLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(42);
        $a = $provider->binaryLiteral();
        $faker->seed(42);
        self::assertSame($a, $provider->binaryLiteral(1, 64));
    }

    public function testHostnameDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new MySqlLiteralProvider($faker);
        $faker->seed(42);
        $a = $provider->hostname();
        $faker->seed(42);
        self::assertSame($a, $provider->hostname(1, 4, 63));
    }

}
