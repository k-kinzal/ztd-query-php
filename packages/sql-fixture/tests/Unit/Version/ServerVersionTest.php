<?php

declare(strict_types=1);

namespace Tests\Unit\Version;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Version\ReleaseNumber;
use SqlFixture\Version\Releases;
use SqlFixture\Version\ServerVersion as Subject;
use SqlFixture\Version\UnsupportedVersionException;

#[CoversClass(Subject::class)]
#[UsesClass(Releases::class)]
#[UsesClass(ReleaseNumber::class)]
#[UsesClass(UnsupportedVersionException::class)]
final class ServerVersionTest extends TestCase
{
    public function testResolveNamesTheDefaultOfEachDialect(): void
    {
        self::assertSame('mysql-8.4.7', Subject::resolve('mysql')->tag);
        self::assertSame('pg-17.2', Subject::resolve('pgsql')->tag);
        self::assertSame('sqlite-3.47.2', Subject::resolve('sqlite')->tag);
    }

    public function testResolveReadsTheNumberOfATag(): void
    {
        $release = Subject::resolve('mysql', 'mysql-5.7.44');
        self::assertSame('mysql', $release->dialect);
        self::assertSame('mysql-5.7.44', $release->tag);
        self::assertSame('5.7.44', $release->number);
    }

    public function testResolveRejectsATagOfAnotherDialect(): void
    {
        $this->expectException(UnsupportedVersionException::class);
        $this->expectExceptionMessage('Unsupported mysql version: pg-17.2');
        Subject::resolve('mysql', 'pg-17.2');
    }

    public function testResolveRejectsAnUnknownDialect(): void
    {
        $this->expectException(UnsupportedVersionException::class);
        $this->expectExceptionMessage('No supported versions are registered for the oracle driver');
        Subject::resolve('oracle');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerServerVersions(): iterable
    {
        yield 'same series with a build suffix' => ['mysql', '8.0.36-0ubuntu0.22.04.1', 'mysql-8.0.44'];
        yield 'same series with a log suffix' => ['mysql', '5.7.44-log', 'mysql-5.7.44'];
        yield 'exact release' => ['mysql', '9.1.0', 'mysql-9.1.0'];
        yield 'newer than every release' => ['mysql', '9.4.0', 'mysql-9.1.0'];
        yield 'older than every release' => ['mysql', '5.5.62', 'mysql-5.6.51'];
        yield 'postgres with a distribution note' => ['pgsql', '17.2 (Debian 17.2-1.pgdg120+1)', 'pg-17.2'];
        yield 'postgres of an older series' => ['pgsql', '16.6', 'pg-17.2'];
        yield 'sqlite newer than the release' => ['sqlite', '3.53.3', 'sqlite-3.47.2'];
        yield 'sqlite older than the release' => ['sqlite', '3.45.1', 'sqlite-3.47.2'];
    }

    #[DataProvider('providerServerVersions')]
    public function testFromServerMatchesTheClosestRelease(string $dialect, string $reported, string $expected): void
    {
        self::assertSame($expected, Subject::fromServer($dialect, $reported)->tag);
    }

    public function testFromServerRejectsAStringWithoutANumber(): void
    {
        $this->expectException(UnsupportedVersionException::class);
        $this->expectExceptionMessage('Unsupported mysql version: unknown');
        Subject::fromServer('mysql', 'unknown');
    }

    public function testAllListsTheReleasesOldestFirst(): void
    {
        $numbers = array_map(static fn (Subject $release): string => $release->number, Subject::all('mysql'));
        self::assertSame(['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'], $numbers);
    }

    public function testTagsMatchTheOtherSqlPackages(): void
    {
        self::assertSame([
            'mysql-5.6.51',
            'mysql-5.7.44',
            'mysql-8.0.44',
            'mysql-8.1.0',
            'mysql-8.2.0',
            'mysql-8.3.0',
            'mysql-8.4.7',
            'mysql-9.0.1',
            'mysql-9.1.0',
        ], Subject::tags('mysql'));
        self::assertSame(['pg-17.2'], Subject::tags('pgsql'));
        self::assertSame(['sqlite-3.47.2'], Subject::tags('sqlite'));
    }

    public function testIsDefaultMarksOneReleasePerDialect(): void
    {
        $defaults = array_values(array_filter(Subject::all('mysql'), static fn (Subject $release): bool => $release->isDefault()));
        self::assertCount(1, $defaults);
        self::assertSame('mysql-8.4.7', $defaults[0]->tag);
    }

    public function testIdNumbersTheReleaseLikeMysql(): void
    {
        self::assertSame(80044, Subject::resolve('mysql', 'mysql-8.0.44')->id());
        self::assertSame(170200, Subject::resolve('pgsql', 'pg-17.2')->id());
    }

    public function testSeriesDropsTheLastNumber(): void
    {
        self::assertSame('8.0', Subject::resolve('mysql', 'mysql-8.0.44')->series());
        self::assertSame('17', Subject::resolve('pgsql', 'pg-17.2')->series());
    }

    public function testIsAtLeastComparesNumbers(): void
    {
        $release = Subject::resolve('mysql', 'mysql-5.7.44');
        self::assertTrue($release->isAtLeast('5.7.8'));
        self::assertTrue($release->isAtLeast('5.7.44'));
        self::assertFalse($release->isAtLeast('8.0'));
    }
}
