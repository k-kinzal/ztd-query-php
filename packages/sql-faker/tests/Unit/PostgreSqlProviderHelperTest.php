<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\PostgreSqlProvider;

#[CoversClass(PostgreSqlProvider::class)]
#[CoversClass(RandomStringGenerator::class)]
final class PostgreSqlProviderHelperTest extends TestCase
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
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->quotedIdentifier();

        self::assertMatchesRegularExpression('/^"[a-z_][a-z0-9_]*"$/', $result);
    }

    public function testStringLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->stringLiteral();

        self::assertMatchesRegularExpression("/^'[a-zA-Z0-9_]{1,32}'$/", $result);
    }

    public function testIntegerLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->integerLiteral();

        self::assertMatchesRegularExpression('/^[1-9]\d*$/', $result);
    }

    public function testDecimalLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->decimalLiteral();

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testFloatLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->floatLiteral();

        self::assertMatchesRegularExpression('/^\d+\.\d+e-?\d+$/', $result);
    }

    public function testHexLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->hexLiteral();

        self::assertMatchesRegularExpression("/^X'[0-9a-f]{1,16}'$/", $result);
    }

    public function testBinaryLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->binaryLiteral();

        self::assertMatchesRegularExpression("/^B'[01]{1,64}'$/", $result);
    }

    public function testDollarQuotedString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->dollarQuotedString();

        self::assertMatchesRegularExpression('/^\$\$[a-zA-Z0-9_]{1,32}\$\$$/', $result);
    }

    public function testParameterMarker(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->parameterMarker();

        self::assertMatchesRegularExpression('/^\$\d+$/', $result);
    }

    public function testQuotedIdentifierDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $result = $provider->quotedIdentifier();
        $faker->seed(42);

        self::assertSame($result, $provider->quotedIdentifier(1, 63));
    }

    public function testStringLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $result = $provider->stringLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->stringLiteral(1, 32));
    }

    public function testIntegerLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $result = $provider->integerLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->integerLiteral(1, 2147483647));
    }

    public function testDecimalLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $result = $provider->decimalLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->decimalLiteral(10, 2));
    }

    public function testFloatLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $result = $provider->floatLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->floatLiteral(10, 2, -307, 308));
    }

    public function testHexLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $result = $provider->hexLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->hexLiteral(1, 16));
    }

    public function testBinaryLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $result = $provider->binaryLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->binaryLiteral(1, 64));
    }

    public function testDollarQuotedStringDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $result = $provider->dollarQuotedString();
        $faker->seed(42);

        self::assertSame($result, $provider->dollarQuotedString(1, 32));
    }

    public function testParameterMarkerDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new PostgreSqlProvider($faker);
        $faker->seed(42);
        $result = $provider->parameterMarker();
        $faker->seed(42);

        self::assertSame($result, $provider->parameterMarker(1, 99));
    }

    public function testQuotedIdentifierCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->quotedIdentifier(5, 10);

        self::assertMatchesRegularExpression('/^"[a-z_][a-z0-9_]{4,9}"$/', $result);
    }

    public function testStringLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->stringLiteral(3, 8);
        $content = substr($result, 1, -1);

        self::assertGreaterThanOrEqual(3, strlen($content));
        self::assertLessThanOrEqual(8, strlen($content));
    }

    public function testIntegerLiteralCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->integerLiteral(100, 500);

        self::assertGreaterThanOrEqual(100, (int) $result);
        self::assertLessThanOrEqual(500, (int) $result);
    }

    public function testDecimalLiteralCustomPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->decimalLiteral(5, 2);

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testFloatLiteralCustomParams(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->floatLiteral(5, 2, -10, 10);

        self::assertMatchesRegularExpression('/^\d+\.\d+e-?\d+$/', $result);
    }

    public function testHexLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->hexLiteral(4, 8);

        self::assertMatchesRegularExpression("/^X'[0-9a-f]{4,8}'$/", $result);
    }

    public function testBinaryLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->binaryLiteral(8, 16);

        self::assertMatchesRegularExpression("/^B'[01]{8,16}'$/", $result);
    }

    public function testDollarQuotedStringCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->dollarQuotedString(2, 6);
        $content = substr($result, 2, -2);

        self::assertGreaterThanOrEqual(2, strlen($content));
        self::assertLessThanOrEqual(6, strlen($content));
    }

    public function testParameterMarkerCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new PostgreSqlProvider($faker);

        $result = $provider->parameterMarker(1, 5);

        self::assertMatchesRegularExpression('/^\$[1-5]$/', $result);
    }
}
