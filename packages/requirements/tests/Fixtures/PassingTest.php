<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PassingTest extends TestCase
{
    public function testPass(): void
    {
        self::assertSame('ABC', strtoupper('abc'));
    }

    public function testPassSuffix(): void
    {
        self::fail('A similarly named test must not be selected.');
    }

    public function testFailure(): void
    {
        self::fail('Expected failure.');
    }

    public function testSkip(): void
    {
        self::markTestSkipped('Expected skip.');
    }

    #[DataProvider('values')]
    public function testData(int $value): void
    {
        self::assertGreaterThan(0, $value);
    }

    /** @return list<array{int}> */
    public static function values(): array
    {
        return [[1], [2]];
    }
}
