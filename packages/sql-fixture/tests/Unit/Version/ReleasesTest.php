<?php

declare(strict_types=1);

namespace Tests\Unit\Version;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Version\Releases as Subject;
use SqlFixture\Version\UnsupportedVersionException;

#[CoversClass(Subject::class)]
#[UsesClass(UnsupportedVersionException::class)]
final class ReleasesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerDialects(): iterable
    {
        yield 'mysql' => ['mysql'];
        yield 'postgres' => ['pgsql'];
        yield 'sqlite' => ['sqlite'];
    }

    #[DataProvider('providerDialects')]
    public function testDefaultTagIsOneOfTheReleases(string $dialect): void
    {
        $releases = new Subject();

        self::assertArrayHasKey($releases->defaultTag($dialect), $releases->numbers($dialect));
    }

    public function testNumbersNameEachTagAfterItsNumber(): void
    {
        $numbers = (new Subject())->numbers('mysql');

        self::assertSame(array_map(static fn (string $number): string => 'mysql-' . $number, array_values($numbers)), array_keys($numbers));
    }

    public function testEntryRejectsAnUnknownDialect(): void
    {
        $this->expectException(UnsupportedVersionException::class);
        (new Subject())->entry('oracle');
    }
}
