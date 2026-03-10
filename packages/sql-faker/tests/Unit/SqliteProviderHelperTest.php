<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\SqliteProvider;

#[CoversClass(SqliteProvider::class)]
#[CoversClass(RandomStringGenerator::class)]
final class SqliteProviderHelperTest extends TestCase
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
        $provider = new SqliteProvider($faker);

        $result = $provider->quotedIdentifier();

        self::assertMatchesRegularExpression('/^"[a-z_][a-z0-9_]*"$/', $result);
    }

    public function testStringLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->stringLiteral();

        self::assertMatchesRegularExpression("/^'[a-zA-Z0-9_]{1,32}'$/", $result);
    }

    public function testIntegerLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->integerLiteral();

        self::assertMatchesRegularExpression('/^[1-9]\d*$/', $result);
    }

    public function testDecimalLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->decimalLiteral();

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }

    public function testQuotedIdentifierDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $faker->seed(42);
        $result = $provider->quotedIdentifier();
        $faker->seed(42);

        self::assertSame($result, $provider->quotedIdentifier(1, 128));
    }

    public function testStringLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $faker->seed(42);
        $result = $provider->stringLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->stringLiteral(1, 32));
    }

    public function testStringLiteralDefaultMatchesExplicitForSensitiveSeed(): void
    {
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $faker->seed(0);
        $default = $provider->stringLiteral();
        $faker->seed(0);
        $explicit = $provider->stringLiteral(1, 32);

        self::assertSame($explicit, $default);
        self::assertSame(13, strlen(substr($default, 1, -1)));
    }

    public function testIntegerLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $faker->seed(42);
        $result = $provider->integerLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->integerLiteral(1, PHP_INT_MAX));
    }

    public function testDecimalLiteralDefaultMatchesExplicit(): void
    {
        $faker = Factory::create();
        $provider = new SqliteProvider($faker);
        $faker->seed(42);
        $result = $provider->decimalLiteral();
        $faker->seed(42);

        self::assertSame($result, $provider->decimalLiteral(15, 2));
    }

    public function testQuotedIdentifierCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->quotedIdentifier(5, 10);

        self::assertMatchesRegularExpression('/^"[a-z_][a-z0-9_]{4,9}"$/', $result);
    }

    public function testStringLiteralCustomLength(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->stringLiteral(3, 8);
        $content = substr($result, 1, -1);

        self::assertGreaterThanOrEqual(3, strlen($content));
        self::assertLessThanOrEqual(8, strlen($content));
    }

    public function testIntegerLiteralCustomRange(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->integerLiteral(100, 500);

        self::assertGreaterThanOrEqual(100, (int) $result);
        self::assertLessThanOrEqual(500, (int) $result);
    }

    public function testDecimalLiteralCustomPrecision(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $provider = new SqliteProvider($faker);

        $result = $provider->decimalLiteral(5, 2);

        self::assertMatchesRegularExpression('/^\d+\.\d{2,}$/', $result);
    }
}
