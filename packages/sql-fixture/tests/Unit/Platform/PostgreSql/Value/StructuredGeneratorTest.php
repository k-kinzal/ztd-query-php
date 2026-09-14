<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Value;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Value\StructuredGenerator as Subject;

#[CoversClass(Subject::class)]
final class StructuredGeneratorTest extends TestCase
{
    public function testGenerateByteaReturnsHexBytea(): void
    {
        self::assertMatchesRegularExpression('/^\\\\x[0-9a-f]+$/', (new Subject())->generateBytea(Factory::create()));
    }

    public function testGenerateIntervalIncludesCalendarAndClockUnits(): void
    {
        self::assertMatchesRegularExpression('/^\d+ (years?|months?|days?|hours?|minutes?|seconds?)$/', (new Subject())->generateInterval(Factory::create()));
    }

    public function testGenerateJsonReturnsAJsonObject(): void
    {
        $json = (new Subject())->generateJson(Factory::create());
        self::assertIsArray(json_decode($json, true));
        self::assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function testGenerateIntArrayUsesPostgresArraySyntax(): void
    {
        self::assertMatchesRegularExpression('/^\{(?:-?\d+(?:,-?\d+)*)?\}$/', (new Subject())->generateIntArray(Factory::create()));
    }

    public function testGenerateTextArrayUsesPostgresArraySyntax(): void
    {
        $value = (new Subject())->generateTextArray(Factory::create());
        self::assertStringStartsWith('{', $value);
        self::assertStringEndsWith('}', $value);
        self::assertStringContainsString('"', $value);
    }
}
